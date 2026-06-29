<?php

namespace ApiArca\App\Repositories;

use ApiArca\App\Core\Database;

/**
 * Repository para gestión de clientes
 */
class CustomerRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene un cliente por ID
     */
    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    /**
     * Obtiene un cliente por CUIT dentro de una empresa
     */
    public function findByCuit(int $companyId, string $cuit): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM customers WHERE company_id = ? AND cuit = ?',
            [$companyId, $cuit]
        );
    }

    /**
     * Obtiene clientes por empresa
     */
    public function findByCompany(int $companyId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->query(
            'SELECT * FROM customers 
             WHERE company_id = ? AND activo = 1
             ORDER BY razon_social 
             LIMIT ? OFFSET ?',
            [$companyId, $limit, $offset]
        );
    }

    /**
     * Crea un nuevo cliente
     */
    public function create(array $data): int
    {
        return $this->db->insert('customers', [
            'company_id' => $data['company_id'],
            'cuit' => $data['cuit'],
            'razon_social' => $data['razon_social'],
            'tipo_documento' => $data['tipo_documento'] ?? 96,
            'condicion_iva' => $data['condicion_iva'] ?? 5,
            'domicilio' => $data['domicilio'] ?? null,
            'localidad' => $data['localidad'] ?? null,
            'provincia' => $data['provincia'] ?? null,
            'codigo_postal' => $data['codigo_postal'] ?? null,
            'pais' => $data['pais'] ?? 10,
            'email' => $data['email'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'activo' => 1,
        ]);
    }

    /**
     * Actualiza un cliente
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('customers', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Desactiva un cliente
     */
    public function deactivate(int $id): int
    {
        return $this->db->update('customers', ['activo' => 0], 'id = :id', ['id' => $id]);
    }

    /**
     * Busca clientes por razón social (búsqueda parcial)
     */
    public function searchByNombre(int $companyId, string $nombre, int $limit = 20): array
    {
        return $this->db->query(
            "SELECT * FROM customers 
             WHERE company_id = ? AND activo = 1
             AND razon_social LIKE ?
             ORDER BY razon_social
             LIMIT ?",
            [$companyId, "%{$nombre}%", $limit]
        );
    }
}
