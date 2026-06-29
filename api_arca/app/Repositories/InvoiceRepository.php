<?php

namespace ApiArca\App\Repositories;

use ApiArca\App\Core\Database;

/**
 * Repository para gestión de facturas/comprobantes
 */
class InvoiceRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene una factura por ID
     */
    public function findById(int $id): ?array
    {
        return $this->db->queryOne('SELECT * FROM invoices WHERE id = ?', [$id]);
    }

    /**
     * Obtiene facturas por empresa
     */
    public function findByCompany(int $companyId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->query(
            'SELECT * FROM invoices 
             WHERE company_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?',
            [$companyId, $limit, $offset]
        );
    }

    /**
     * Crea una nueva factura
     */
    public function create(array $data): int
    {
        return $this->db->insert('invoices', [
            'company_id' => $data['company_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'tipo_comprobante' => $data['tipo_comprobante'],
            'punto_venta' => $data['punto_venta'],
            'numero_comprobante' => $data['numero_comprobante'] ?? null,
            'cae' => $data['cae'] ?? null,
            'cae_fecavto' => isset($data['cae_fecavto']) && $data['cae_fecavto'] instanceof \DateTime 
                ? $data['cae_fecavto']->format('Y-m-d H:i:s') 
                : null,
            'estado' => $data['estado'] ?? 'PENDING',
            'total' => $data['total'],
            'moneda' => $data['moneda'] ?? 'PES',
            'cotizacion' => $data['cotizacion'] ?? 1.0,
            'observaciones' => $data['observaciones'] ?? null,
            'xml_request' => $data['xml_request'] ?? null,
            'xml_response' => $data['xml_response'] ?? null,
        ]);
    }

    /**
     * Actualiza una factura
     */
    public function update(int $id, array $data): int
    {
        return $this->db->update('invoices', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Busca una factura por CAE
     */
    public function findByCae(string $cae): ?array
    {
        return $this->db->queryOne('SELECT * FROM invoices WHERE cae = ?', [$cae]);
    }

    /**
     * Obtiene estadísticas de facturas por empresa
     */
    public function getStats(int $companyId, string $fromDate, string $toDate): array
    {
        return $this->db->queryOne(
            'SELECT 
                COUNT(*) as total_facturas,
                SUM(total) as monto_total,
                SUM(CASE WHEN estado = "APPROVED" THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado = "REJECTED" THEN 1 ELSE 0 END) as rechazadas
             FROM invoices
             WHERE company_id = ?
             AND DATE(created_at) BETWEEN ? AND ?',
            [$companyId, $fromDate, $toDate]
        );
    }
}
