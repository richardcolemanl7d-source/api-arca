<?php

/**
 * API ARCA Gateway - Punto de entrada principal
 * 
 * Este archivo bootstrap inicializa la aplicación y maneja todas las solicitudes HTTP
 */

declare(strict_types=1);

// Definir constante de raíz del proyecto
define('ROOT_PATH', dirname(__DIR__));

// Cargar autoload de Composer
require_once ROOT_PATH . '/vendor/autoload.php';

// Cargar variables de entorno
\ApiArca\App\Helpers\EnvLoader::load(ROOT_PATH . '/.env');

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Core\Router;
use ApiArca\App\Core\Database;
use ApiArca\App\Core\Container;
use ApiArca\App\Helpers\Logger;

// Manejo de errores global
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
});

set_exception_handler(function (\Throwable $e) {
    $response = Response::error(
        'Error interno del servidor',
        [env('APP_DEBUG', false) ? $e->getMessage() : ''],
        500
    );
    $response->send();
});

try {
    // Inicializar contenedor de dependencias
    $container = Container::getInstance();

    // Registrar servicios en el contenedor
    $container->singleton(\ApiArca\App\Core\Database::class, function () {
        return Database::getInstance();
    });

    $container->singleton(\ApiArca\App\Helpers\Logger::class, function () {
        $config = require ROOT_PATH . '/config/app.php';
        return new Logger(
            $config['logging']['path'] ?? ROOT_PATH . '/storage/logs',
            env('LOG_LEVEL', 'info')
        );
    });

    $container->singleton(\ApiArca\App\Crypto\AESService::class, function () {
        return new \ApiArca\App\Crypto\AESService(env('ARCA_MASTER_KEY'));
    });

    // Repositories
    $container->bind(\ApiArca\App\Repositories\CompanyRepository::class, function ($c) {
        return new \ApiArca\App\Repositories\CompanyRepository($c->make(\ApiArca\App\Core\Database::class));
    });

    $container->bind(\ApiArca\App\Repositories\ApiKeyRepository::class, function ($c) {
        return new \ApiArca\App\Repositories\ApiKeyRepository($c->make(\ApiArca\App\Core\Database::class));
    });

    $container->bind(\ApiArca\App\Repositories\TokenRepository::class, function ($c) {
        return new \ApiArca\App\Repositories\TokenRepository($c->make(\ApiArca\App\Core\Database::class));
    });

    $container->bind(\ApiArca\App\Repositories\InvoiceRepository::class, function ($c) {
        return new \ApiArca\App\Repositories\InvoiceRepository($c->make(\ApiArca\App\Core\Database::class));
    });

    $container->bind(\ApiArca\App\Repositories\CustomerRepository::class, function ($c) {
        return new \ApiArca\App\Repositories\CustomerRepository($c->make(\ApiArca\App\Core\Database::class));
    });

    // Services
    $container->bind(\ApiArca\App\Services\CertificateService::class, function ($c) {
        return new \ApiArca\App\Services\CertificateService(
            $c->make(\ApiArca\App\Repositories\CompanyRepository::class),
            $c->make(\ApiArca\App\Crypto\AESService::class),
            $c->make(\ApiArca\App\Helpers\Logger::class)
        );
    });

    $container->bind(\ApiArca\App\Services\WsaaService::class, function ($c) {
        return new \ApiArca\App\Services\WsaaService(
            $c->make(\ApiArca\App\Services\CertificateService::class),
            $c->make(\ApiArca\App\Helpers\Logger::class)
        );
    });

    $container->bind(\ApiArca\App\Services\TokenService::class, function ($c) {
        return new \ApiArca\App\Services\TokenService(
            $c->make(\ApiArca\App\Repositories\TokenRepository::class),
            $c->make(\ApiArca\App\Services\WsaaService::class),
            $c->make(\ApiArca\App\Helpers\Logger::class)
        );
    });

    $container->bind(\ApiArca\App\Services\WsfeService::class, function ($c) {
        return new \ApiArca\App\Services\WsfeService(
            $c->make(\ApiArca\App\Services\TokenService::class),
            $c->make(\ApiArca\App\Helpers\Logger::class)
        );
    });

    $container->bind(\ApiArca\App\Services\InvoiceService::class, function ($c) {
        return new \ApiArca\App\Services\InvoiceService(
            $c->make(\ApiArca\App\Repositories\InvoiceRepository::class),
            $c->make(\ApiArca\App\Repositories\CustomerRepository::class),
            $c->make(\ApiArca\App\Services\WsfeService::class),
            $c->make(\ApiArca\App\Services\TokenService::class),
            $c->make(\ApiArca\App\Helpers\Logger::class)
        );
    });

    // Middlewares
    $container->bind(\ApiArca\App\Middleware\AuthMiddleware::class, function ($c) {
        return new \ApiArca\App\Middleware\AuthMiddleware(
            $c->make(\ApiArca\App\Repositories\ApiKeyRepository::class),
            $c->make(\ApiArca\App\Helpers\Logger::class)
        );
    });

    $container->bind(\ApiArca\App\Middleware\TenantMiddleware::class, function () {
        return new \ApiArca\App\Middleware\TenantMiddleware();
    });

    $container->bind(\ApiArca\App\Middleware\RateLimitMiddleware::class, function () {
        return new \ApiArca\App\Middleware\RateLimitMiddleware();
    });

    // Crear router y cargar rutas
    $router = new Router();
    
    // Agregar middleware global de rate limiting (opcional)
    // $router->use(\ApiArca\App\Middleware\RateLimitMiddleware::class);

    // Cargar definición de rutas
    $routesFile = ROOT_PATH . '/routes/api.php';
    if (file_exists($routesFile)) {
        require $routesFile;
    }

    // Crear request desde globals
    $request = new Request();

    // Despachar request al router
    $response = $router->dispatch($request);

    // Enviar respuesta
    $response->send();

} catch (\Throwable $e) {
    // Capturar cualquier error no manejado
    $debug = env('APP_DEBUG', false);
    
    $response = Response::error(
        'Error interno del servidor',
        $debug ? [$e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine()] : [],
        500
    );
    $response->send();
}
