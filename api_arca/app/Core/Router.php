<?php

namespace ApiArca\App\Core;

/**
 * Router para manejar el enrutamiento de solicitudes HTTP
 */
class Router
{
    private array $routes = [];
    private array $middlewareGroups = [];
    private array $globalMiddleware = [];

    /**
     * Registra una ruta GET
     * 
     * @param string $path Ruta URL
     * @param callable|array $handler Controlador o callback
     * @param array $middleware Middlewares para esta ruta
     * @return self
     */
    public function get(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Registra una ruta POST
     * 
     * @param string $path Ruta URL
     * @param callable|array $handler Controlador o callback
     * @param array $middleware Middlewares para esta ruta
     * @return self
     */
    public function post(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Registra una ruta PUT
     * 
     * @param string $path Ruta URL
     * @param callable|array $handler Controlador o callback
     * @param array $middleware Middlewares para esta ruta
     * @return self
     */
    public function put(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Registra una ruta DELETE
     * 
     * @param string $path Ruta URL
     * @param callable|array $handler Controlador o callback
     * @param array $middleware Middlewares para esta ruta
     * @return self
     */
    public function delete(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Agrega una ruta al router
     * 
     * @param string $method Método HTTP
     * @param string $path Ruta URL
     * @param callable|array $handler Controlador o callback
     * @param array $middleware Middlewares para esta ruta
     * @return self
     */
    private function addRoute(string $method, string $path, callable|array $handler, array $middleware = []): self
    {
        // Normalizar path
        $path = '/' . trim($path, '/');
        
        // Convertir handler a formato estándar [clase, método]
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler);
            $handler = [$class, $method];
        }

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->convertToRegex($path),
            'handler' => $handler,
            'middleware' => array_merge($this->globalMiddleware, $middleware),
        ];

        return $this;
    }

    /**
     * Convierte una ruta con parámetros a expresión regular
     * 
     * @param string $path
     * @return string
     */
    private function convertToRegex(string $path): string
    {
        // Reemplazar {param} con captura regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        
        return '#^' . $pattern . '$#';
    }

    /**
     * Registra un grupo de middleware
     * 
     * @param string $name Nombre del grupo
     * @param array $middleware Lista de middlewares
     * @return self
     */
    public function middlewareGroup(string $name, array $middleware): self
    {
        $this->middlewareGroups[$name] = $middleware;
        return $this;
    }

    /**
     * Agrega middleware global
     * 
     * @param string $middleware
     * @return self
     */
    public function use(string $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Despacha la solicitud al controlador correspondiente
     * 
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        $route = $this->matchRoute($request);

        if ($route === null) {
            return Response::error('Ruta no encontrada', [], 404);
        }

        // Ejecutar middlewares en cadena
        $handler = $route['handler'];
        $middleware = $route['middleware'];

        $next = function (Request $req) use ($handler) {
            return $this->callHandler($handler, $req);
        };

        // Invertir middlewares para ejecutar en orden correcto
        foreach (array_reverse($middleware) as $mw) {
            $middlewareClass = new $mw();
            $currentNext = $next;
            
            $next = function (Request $req) use ($middlewareClass, $currentNext) {
                return $middlewareClass->handle($req, $currentNext);
            };
        }

        return $next($request);
    }

    /**
     * Busca una ruta que coincida con la solicitud
     * 
     * @param Request $request
     * @return array|null
     */
    private function matchRoute(Request $request): ?array
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extraer parámetros nombrados
                $params = array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
                $request->setRouteParams($params);
                
                return $route;
            }
        }

        return null;
    }

    /**
     * Llama al handler del controlador
     * 
     * @param array $handler [clase, método]
     * @param Request $request
     * @return Response
     */
    private function callHandler(array $handler, Request $request): Response
    {
        [$class, $method] = $handler;

        if (!class_exists($class)) {
            return Response::error("Controlador {$class} no encontrado", [], 500);
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            return Response::error("Método {$method} no encontrado en {$class}", [], 500);
        }

        return $controller->$method($request);
    }

    /**
     * Obtiene todas las rutas registradas
     * 
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
