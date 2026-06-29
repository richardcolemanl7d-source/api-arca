<?php

namespace ApiArca\App\Middleware;

use ApiArca\App\Core\Request;
use ApiArca\App\Core\Response;
use ApiArca\App\Core\Middleware;

/**
 * Middleware de Rate Limiting
 * Limita la cantidad de requests por minuto
 */
class RateLimitMiddleware extends Middleware
{
    private const MAX_REQUESTS = 100;
    private const PERIOD_SECONDS = 60;

    /**
     * Procesa la solicitud verificando el rate limit
     */
    public function handle(Request $request, callable $next): Response
    {
        // Obtener identificador único (IP + API Key si existe)
        $apiKey = $request->getHeader('x-api-key');
        $identifier = $apiKey ? 'apikey_' . md5($apiKey) : 'ip_' . ($this->getClientIp() ?? 'unknown');

        // Verificar límite usando almacenamiento en memoria o archivo
        if (!$this->checkRateLimit($identifier)) {
            return Response::error('Demasiadas solicitudes. Intente más tarde.', [], 429);
        }

        return $next($request);
    }

    /**
     * Verifica el rate limit para un identificador
     * Implementación simple usando archivos (se puede reemplazar con Redis)
     */
    private function checkRateLimit(string $identifier): bool
    {
        $cacheDir = __DIR__ . '/../../storage/cache/ratelimit';
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . '/' . md5($identifier);
        $now = time();
        $windowStart = $now - self::PERIOD_SECONDS;

        // Leer datos existentes
        $data = ['hits' => [], 'blocked' => false];
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        // Limpiar hits antiguos fuera de la ventana
        $data['hits'] = array_filter($data['hits'], fn($time) => $time > $windowStart);

        // Verificar si está bloqueado
        if ($data['blocked'] && !empty($data['hits'])) {
            // Aún dentro del período de bloqueo
            return false;
        }

        // Resetear bloqueo si pasó el período
        if ($data['blocked'] && empty($data['hits'])) {
            $data['blocked'] = false;
        }

        // Agregar hit actual
        $data['hits'][] = $now;

        // Verificar límite
        if (count($data['hits']) > self::MAX_REQUESTS) {
            $data['blocked'] = true;
            $this->saveRateLimitData($file, $data);
            return false;
        }

        // Guardar datos actualizados
        $this->saveRateLimitData($file, $data);
        
        return true;
    }

    /**
     * Guarda los datos de rate limit
     */
    private function saveRateLimitData(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Obtiene la IP del cliente
     */
    private function getClientIp(): ?string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }
}
