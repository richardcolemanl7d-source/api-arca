<?php

namespace ApiArca\App\Repositories;

use ApiArca\App\Core\Database;

/**
 * Repository para gestión de tokens ARCA/AFIP
 */
class TokenRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene un token por empresa y servicio
     */
    public function findByCompanyAndService(int $companyId, string $service): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM tokens 
             WHERE company_id = ? AND token_type = ? 
             ORDER BY created_at DESC LIMIT 1',
            [$companyId, $service]
        );
    }

    /**
     * Inserta o actualiza un token (upsert)
     */
    public function upsert(array $data): int
    {
        // Verificar si existe
        $existing = $this->findByCompanyAndService($data['company_id'], $data['token_type']);

        if ($existing !== null) {
            // Actualizar existente
            $this->db->update('tokens', [
                'access_token' => $data['access_token'],
                'sign_token' => $data['sign_token'],
                'expiration_time' => $data['expiration_time'],
            ], 'id = :id', ['id' => $existing['id']]);
            
            return $existing['id'];
        }

        // Insertar nuevo
        return $this->db->insert('tokens', [
            'company_id' => $data['company_id'],
            'token_type' => $data['token_type'],
            'access_token' => $data['access_token'],
            'sign_token' => $data['sign_token'],
            'expiration_time' => $data['expiration_time'],
        ]);
    }

    /**
     * Elimina tokens expirados
     */
    public function deleteExpired(): int
    {
        return $this->db->delete(
            'tokens',
            'expiration_time < NOW()'
        );
    }

    /**
     * Elimina todos los tokens de una empresa
     */
    public function deleteByCompany(int $companyId): int
    {
        return $this->db->delete('tokens', 'company_id = :company_id', [
            'company_id' => $companyId
        ]);
    }
}
