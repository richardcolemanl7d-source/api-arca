<?php

namespace ApiArca\App\Controllers;

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Helpers\Logger;

/**
 * Controlador para autenticación y gestión de API Keys
 */
class AuthController
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Login para obtener información de la API Key
     * POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        try {
            // En este sistema, el login se realiza mediante API Key en el header
            // Este endpoint es informativo
            
            $apiKey = $request->getHeader('x-api-key');
            
            if (empty($apiKey)) {
                return Response::error('API Key requerida en header X-API-KEY', [], 400);
            }

            // La validación real la hace AuthMiddleware
            // Aquí solo devolvemos información sobre la key actual
            
            return Response::success([
                'message' => 'Autenticación exitosa',
                'api_key_prefix' => substr($apiKey, 0, 8) . '...',
            ], 'Login exitoso');

        } catch (\Exception $e) {
            $this->logger->error('Error en login', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }

    /**
     * Verifica el estado del sistema
     * GET /api/status
     */
    public function status(Request $request): Response
    {
        try {
            return Response::success([
                'status' => 'ok',
                'version' => '1.0.0',
                'timestamp' => date('Y-m-d H:i:s'),
                'ambiente' => env('ARCA_AMBIENTE', 'homologacion'),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error verificando status', ['error' => $e->getMessage()]);
            return Response::error('Error interno del servidor', [], 500);
        }
    }
}
