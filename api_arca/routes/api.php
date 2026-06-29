<?php

/**
 * Definición de rutas de la API
 */

use ApiArca\App\Controllers\AuthController;
use ApiArca\App\Controllers\CompanyController;
use ApiArca\App\Controllers\CustomerController;
use ApiArca\App\Controllers\InvoiceController;
use ApiArca\App\Middleware\AuthMiddleware;
use ApiArca\App\Middleware\TenantMiddleware;

// Router instance (se inyectará desde el bootstrap)
$router = $router ?? null;

if ($router !== null) {
    // Rutas públicas (sin autenticación)
    $router->get('/api/status', [AuthController::class, 'status']);
    
    // Rutas protegidas (requieren API Key)
    // Login/verificación de API Key
    $router->post('/api/auth/login', [AuthController::class, 'login'], [AuthMiddleware::class]);
    
    // Empresas
    $router->post('/api/company', [CompanyController::class, 'store']);
    $router->get('/api/company', [CompanyController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/api/company/{id}', [CompanyController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/api/company/upload-certificate', [CompanyController::class, 'uploadCertificate'], [AuthMiddleware::class]);
    
    // Clientes
    $router->get('/api/customer/{cuit}', [CustomerController::class, 'getByCuit'], [AuthMiddleware::class, TenantMiddleware::class]);
    $router->get('/api/customer', [CustomerController::class, 'index'], [AuthMiddleware::class, TenantMiddleware::class]);
    $router->get('/api/customer/search', [CustomerController::class, 'search'], [AuthMiddleware::class, TenantMiddleware::class]);
    
    // Facturas
    $router->post('/api/invoice', [InvoiceController::class, 'store'], [AuthMiddleware::class, TenantMiddleware::class]);
    $router->post('/api/credit-note', [InvoiceController::class, 'creditNote'], [AuthMiddleware::class, TenantMiddleware::class]);
    $router->get('/api/invoice/{id}', [InvoiceController::class, 'show'], [AuthMiddleware::class, TenantMiddleware::class]);
    $router->get('/api/invoice/{id}/status', [InvoiceController::class, 'status'], [AuthMiddleware::class, TenantMiddleware::class]);
}

return $router ?? null;
