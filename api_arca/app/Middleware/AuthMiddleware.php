<?php

namespace ApiArca\App\Middleware;

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Core\Middleware;
use ApiArca\App\Repositories\ApiKeyRepository;
use ApiArca\App\Helpers\Logger;

/**
 * Middleware de autenticación por API Key
 */
class AuthMiddleware extends Middleware
{
    private ApiKeyRepository $apiKeyRepository;
    private Logger $logger;

    public function __construct(
        ApiKeyRepository $apiKeyRepository,
        Logger $logger
    ) {
        $this->apiKeyRepository = $apiKeyRepository;
        $this->logger = $logger;
    }

    /**
     * Procesa la solicitud verificando la API Key
     */
    public function handle(Request $request, callable $next): Response
    {
        // Obtener API Key del header
        $apiKey = $request->getHeader('x-api-key');

        if (empty($apiKey)) {
            $this->logger->warning('Intento de acceso sin API Key', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'uri' => $request->getUri()
            ]);

            return Response::error('API Key requerida', [], 401);
        }

        // Buscar API Key en base de datos
        $keyData = $this->apiKeyRepository->findByKey($apiKey);

        if ($keyData === null) {
            $this->logger->warning('API Key inválida', [
                'api_key_prefix' => substr($apiKey, 0, 8) . '...',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return Response::error('API Key inválida', [], 401);
        }

        // Verificar rate limit
        $rateLimitEnabled = env('RATE_LIMIT_ENABLED', true);
        
        if ($rateLimitEnabled) {
            $maxRequests = $keyData['rate_limit'] ?? 100;
            $periodSeconds = $keyData['rate_limit_period'] ?? 60;

            if (!$this->apiKeyRepository->checkRateLimit($keyData['id'], $maxRequests, $periodSeconds)) {
                $this->logger->warning('Rate limit excedido', [
                    'company_id' => $keyData['company_id'],
                    'api_key_id' => $keyData['id']
                ]);

                return Response::error('Límite de requests excedido. Intente más tarde.', [], 429);
            }
        }

        // Actualizar último uso
        $this->apiKeyRepository->updateLastUsed($keyData['id']);

        // Agregar información de la empresa al request para uso posterior
        $request->setRouteParams(array_merge($request->getRouteParams(), [
            'company_id' => $keyData['company_id'],
            'api_key_id' => $keyData['id'],
            'cuit' => $keyData['cuit'],
        ]));

        $this->logger->debug('Autenticación exitosa', [
            'company_id' => $keyData['company_id'],
            'api_key_id' => $keyData['id']
        ]);

        return $next($request);
    }
}
