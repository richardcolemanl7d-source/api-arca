<?php

namespace ApiArca\App\Core;

/**
 * Clase Request para manejar la solicitud HTTP entrante
 */
class Request
{
    private array $query = [];
    private array $body = [];
    private array $headers = [];
    private string $method;
    private string $uri;
    private array $routeParams = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->query = $_GET ?? [];
        $this->headers = $this->parseHeaders();
        $this->body = $this->parseBody();
    }

    /**
     * Parsea los headers HTTP
     * 
     * @return array
     */
    private function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }

        // Normalizar nombres de headers a minúsculas
        return array_change_key_case($headers, CASE_LOWER);
    }

    /**
     * Parsea el cuerpo de la solicitud según Content-Type
     * 
     * @return array
     */
    private function parseBody(): array
    {
        $contentType = $this->getHeader('content-type', '');
        
        // JSON
        if (strpos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $decoded = json_decode($rawBody, true);
            
            return is_array($decoded) ? $decoded : [];
        }

        // Form data
        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false ||
            strpos($contentType, 'multipart/form-data') !== false) {
            return $_POST ?? [];
        }

        // Default: intentar JSON primero, luego form data
        $rawBody = file_get_contents('php://input');
        $decoded = json_decode($rawBody, true);
        
        if (is_array($decoded)) {
            return $decoded;
        }

        return $_POST ?? [];
    }

    /**
     * Obtiene el método HTTP
     * 
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Obtiene la URI de la solicitud
     * 
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Obtiene un header específico
     * 
     * @param string $name Nombre del header
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Obtiene todos los headers
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Obtiene un parámetro de query string
     * 
     * @param string $name Nombre del parámetro
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    public function query(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    /**
     * Obtiene todos los parámetros de query string
     * 
     * @return array
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    /**
     * Obtiene un campo del body
     * 
     * @param string $name Nombre del campo
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    public function input(string $name, mixed $default = null): mixed
    {
        return $this->body[$name] ?? $default;
    }

    /**
     * Obtiene todos los campos del body
     * 
     * @return array
     */
    public function allInput(): array
    {
        return $this->body;
    }

    /**
     * Obtiene un parámetro de ruta
     * 
     * @param string $name Nombre del parámetro
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    public function route(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    /**
     * Establece los parámetros de ruta
     * 
     * @param array $params
     * @return self
     */
    public function setRouteParams(array $params): self
    {
        $this->routeParams = $params;
        return $this;
    }

    /**
     * Obtiene todos los parámetros de ruta
     * 
     * @return array
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Verifica si la solicitud es AJAX
     * 
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Verifica si la solicitud espera JSON
     * 
     * @return bool
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeader('accept', '');
        return strpos($accept, 'application/json') !== false;
    }
}
