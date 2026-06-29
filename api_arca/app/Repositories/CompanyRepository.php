<?php

namespace ApiArca\App\Repositories;

use ApiArca\App\Core\Database;

/**
 * Repository para gestión de empresas (companies)
 */
class CompanyRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene una empresa por ID
     */
    public function findById(int $id): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM companies WHERE id = ? AND activo = 1',
            [$id]
        );
    }

    /**
     * Obtiene una empresa por CUIT
     */
    public function findByCuit(string $cuit): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM companies WHERE cuit = ?',
            [$cuit]
        );
    }

    /**
     * Obtiene todas las empresas activas
     */
    public function findAllActive(): array
    {
        return $this->db->query(
            'SELECT id, cuit, razon_social, fecha_vencimiento_certificado, created_at 
             FROM companies 
             WHERE activo = 1 
             ORDER BY razon_social'
        );
    }

    /**
     * Crea una nueva empresa
     */
    public function create(array $data): int
    {
        return $this->db->insert('companies', [
            'cuit' => $data['cuit'],
            'razon_social' => $data['razon_social'],
            'certificado_encriptado' => $data['certificado_encriptado'],
            'private_key_encriptada' => $data['private_key_encriptada'],
            'iv' => $data['iv'],
            'tag' => $data['tag'],
            'activo' => $data['activo'] ?? 1,
            'fecha_vencimiento_certificado' => $data['fecha_vencimiento_certificado'],
        ]);
    }

    /**
     * Actualiza una empresa
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('companies', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Actualiza el certificado de una empresa
     */
    public function updateCertificate(int $id, string $certEncrypted, string $keyEncrypted, string $iv, string $tag, \DateTime $expiration): int
    {
        return $this->db->update('companies', [
            'certificado_encriptado' => $certEncrypted,
            'private_key_encriptada' => $keyEncrypted,
            'iv' => $iv,
            'tag' => $tag,
            'fecha_vencimiento_certificado' => $expiration->format('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    /**
     * Desactiva una empresa
     */
    public function deactivate(int $id): int
    {
        return $this->db->update('companies', ['activo' => 0], 'id = :id', ['id' => $id]);
    }

    /**
     * Verifica si existe una empresa con el CUIT dado
     */
    public function existsByCuit(string $cuit, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM companies WHERE cuit = ?';
        $params = [$cuit];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $result = $this->db->queryOne($sql, $params);
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Obtiene empresas con certificados próximos a vencer
     */
    public function getExpiringCertificates(int $days = 30): array
    {
        return $this->db->query(
            "SELECT id, cuit, razon_social, fecha_vencimiento_certificado 
             FROM companies 
             WHERE activo = 1 
             AND fecha_vencimiento_certificado <= DATE_ADD(NOW(), INTERVAL ? DAY)
             ORDER BY fecha_vencimiento_certificado",
            [$days]
        );
    }
}
