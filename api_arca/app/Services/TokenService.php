<?php

namespace ApiArca\App\Services;

use ApiArca\App\Repositories\TokenRepository;
use ApiArca\App\Helpers\Logger;

/**
 * Servicio para gestión de tokens ARCA/AFIP
 */
class TokenService
{
    private TokenRepository $tokenRepository;
    private WsaaService $wsaaService;
    private Logger $logger;

    public function __construct(
        TokenRepository $tokenRepository,
        WsaaService $wsaaService,
        Logger $logger
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->wsaaService = $wsaaService;
        $this->logger = $logger;
    }

    /**
     * Obtiene un token válido para una empresa y servicio
     * Si no existe o está expirado, lo renueva
     * 
     * @param int $companyId ID de la empresa
     * @param string $service Servicio (ej: wsfe)
     * @return array ['access_token' => string, 'sign_token' => string]
     */
    public function obtenerTA(int $companyId, string $service): array
    {
        // Buscar token existente
        $token = $this->tokenRepository->findByCompanyAndService($companyId, $service);

        // Si existe y no está expirado, retornarlo
        if ($token !== null && !$this->TAExpirado($token)) {
            return [
                'access_token' => $token['access_token'],
                'sign_token' => $token['sign_token'],
            ];
        }

        // Renovar token
        return $this->renovarTA($companyId, $service);
    }

    /**
     * Renueva el token de acceso
     * 
     * @param int $companyId ID de la empresa
     * @param string $service Servicio
     * @return array Tokens nuevos
     */
    public function renovarTA(int $companyId, string $service): array
    {
        try {
            // Obtener nuevos tokens desde WSAA
            $tokens = $this->wsaaService->obtenerToken($companyId, $service);

            // Guardar en base de datos
            $this->guardarTA($companyId, $service, $tokens);

            $this->logger->info('Token renovado', [
                'company_id' => $companyId,
                'service' => $service
            ]);

            return [
                'access_token' => $tokens['access_token'],
                'sign_token' => $tokens['sign_token'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error renovando token', [
                'company_id' => $companyId,
                'service' => $service,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Guarda un token en la base de datos
     * 
     * @param int $companyId ID de la empresa
     * @param string $service Servicio
     * @param array $tokens Tokens a guardar
     * @return void
     */
    public function guardarTA(int $companyId, string $service, array $tokens): void
    {
        $this->tokenRepository->upsert([
            'company_id' => $companyId,
            'token_type' => $service,
            'access_token' => $tokens['access_token'],
            'sign_token' => $tokens['sign_token'],
            'expiration_time' => $tokens['expiration']->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Verifica si un token está expirado
     * Se considera expirado 5 minutos antes del vencimiento real por seguridad
     * 
     * @param array $token Datos del token
     * @return bool
     */
    public function TAExpirado(array $token): bool
    {
        if (!isset($token['expiration_time'])) {
            return true;
        }

        $expiration = new \DateTime($token['expiration_time']);
        $now = new \DateTime();
        
        // Restar 5 minutos por seguridad
        $expiration->modify('-5 minutes');

        return $now > $expiration;
    }

    /**
     * Elimina tokens expirados de la base de datos
     * 
     * @return int Número de tokens eliminados
     */
    public function limpiarTokensExpirados(): int
    {
        return $this->tokenRepository->deleteExpired();
    }

    /**
     * Fuerza la renovación de todos los tokens de una empresa
     * 
     * @param int $companyId ID de la empresa
     * @return void
     */
    public function forzarRenovacion(int $companyId): void
    {
        $services = ['wsfe', 'padron'];

        foreach ($services as $service) {
            try {
                $this->renovarTA($companyId, $service);
            } catch (\Exception $e) {
                $this->logger->warning('No se pudo renovar token', [
                    'company_id' => $companyId,
                    'service' => $service,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
