<?php

namespace ApiArca\App\Repositories;

use ApiArca\App\Core\Database;

/**
 * Repository para gestión de API Keys
 */
class ApiKeyRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene una API Key por su valor
     */
    public function findByKey(string $apiKey): ?array
    {
        return $this->db->queryOne(
            'SELECT ak.*, c.cuit, c.razon_social 
             FROM api_keys ak
             INNER JOIN companies c ON ak.company_id = c.id
             WHERE ak.api_key = ? AND ak.activo = 1 AND c.activo = 1',
            [$apiKey]
        );
    }

    /**
     * Obtiene una API Key por ID
     */
    public function findById(int $id): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM api_keys WHERE id = ?',
            [$id]
        );
    }

    /**
     * Obtiene todas las API Keys de una empresa
     */
    public function findByCompany(int $companyId): array
    {
        return $this->db->query(
            'SELECT id, name, api_key, activo, rate_limit, last_used_at, created_at 
             FROM api_keys 
             WHERE company_id = ? 
             ORDER BY created_at DESC',
            [$companyId]
        );
    }

    /**
     * Crea una nueva API Key
     */
    public function create(array $data): int
    {
        return $this->db->insert('api_keys', [
            'company_id' => $data['company_id'],
            'api_key' => $data['api_key'],
            'name' => $data['name'],
            'activo' => $data['activo'] ?? 1,
            'rate_limit' => $data['rate_limit'] ?? 100,
            'rate_limit_period' => $data['rate_limit_period'] ?? 60,
        ]);
    }

    /**
     * Actualiza una API Key
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('api_keys', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Actualiza la fecha de último uso
     */
    public function updateLastUsed(int $id): int
    {
        return $this->db->update(
            'api_keys',
            ['last_used_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Desactiva una API Key
     */
    public function deactivate(int $id): int
    {
        return $this->db->update('api_keys', ['activo' => 0], 'id = :id', ['id' => $id]);
    }

    /**
     * Verifica si existe una API Key con el valor dado
     */
    public function existsByKey(string $apiKey, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM api_keys WHERE api_key = ?';
        $params = [$apiKey];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $result = $this->db->queryOne($sql, $params);
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Genera una nueva API Key única
     */
    public static function generateKey(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }

    /**
     * Valida el rate limit para una API Key
     */
    public function checkRateLimit(int $id, int $maxRequests, int $periodSeconds): bool
    {
        // Implementación básica usando tabla rate_limits
        $identifier = 'api_key_' . $id;
        $now = new \DateTime();
        $resetTime = (clone $now)->modify("+{$periodSeconds} seconds");

        // Buscar registro existente
        $existing = $this->db->queryOne(
            'SELECT * FROM rate_limits WHERE identifier = ?',
            [$identifier]
        );

        if ($existing === null) {
            // Crear nuevo registro
            $this->db->insert('rate_limits', [
                'identifier' => $identifier,
                'hits' => 1,
                'reset_at' => $resetTime->format('Y-m-d H:i:s'),
            ]);
            return true;
        }

        // Verificar si el período expiró
        $resetAt = new \DateTime($existing['reset_at']);
        
        if ($now > $resetAt) {
            // Resetear contador
            $this->db->update('rate_limits', [
                'hits' => 1,
                'reset_at' => $resetTime->format('Y-m-d H:i:s'),
            ], 'identifier = :identifier', ['identifier' => $identifier]);
            return true;
        }

        // Verificar límite
        if ($existing['hits'] >= $maxRequests) {
            return false;
        }

        // Incrementar contador
        $this->db->update(
            'rate_limits',
            ['hits' => $existing['hits'] + 1],
            'identifier = :identifier',
            ['identifier' => $identifier]
        );

        return true;
    }
}
