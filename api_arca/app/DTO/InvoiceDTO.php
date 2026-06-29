<?php

namespace ApiArca\App\DTO;

/**
 * Data Transfer Object para Invoice
 */
class InvoiceDTO
{
    // Tipos de comprobantes según ARCA/AFIP
    public const TIPO_FACTURA_A = 1;
    public const TIPO_FACTURA_B = 6;
    public const TIPO_NOTA_CREDITO_A = 3;
    public const TIPO_NOTA_CREDITO_B = 8;
    public const TIPO_NOTA_DEBITO_A = 2;
    public const TIPO_NOTA_DEBITO_B = 7;

    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $companyId = null,
        public readonly ?int $customerId = null,
        public readonly int $tipoComprobante = 0,
        public readonly int $puntoVenta = 0,
        public readonly ?int $numeroComprobante = null,
        public readonly ?string $cae = null,
        public readonly ?\DateTime $caeFecavto = null,
        public readonly string $estado = 'PENDING', // PENDING, APPROVED, REJECTED
        public readonly float $total = 0.0,
        public readonly string $moneda = 'PES',
        public readonly float $cotizacion = 1.0,
        public readonly ?string $observaciones = null,
        public readonly ?string $xmlRequest = null,
        public readonly ?string $xmlResponse = null,
        public readonly ?\DateTime $createdAt = null,
        public readonly ?\DateTime $updatedAt = null,
        // Datos del cliente (para validación)
        public readonly ?string $clienteCuit = null,
        public readonly ?string $clienteRazonSocial = null,
        public readonly ?int $clienteTipoDocumento = null,
        public readonly ?int $clienteCondicionIva = null,
        // Detalles de la factura
        public readonly array $detalles = [],
    ) {}

    /**
     * Crea un DTO desde un array de base de datos
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            companyId: isset($data['company_id']) ? (int)$data['company_id'] : null,
            customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : null,
            tipoComprobante: (int)($data['tipo_comprobante'] ?? 0),
            puntoVenta: (int)($data['punto_venta'] ?? 0),
            numeroComprobante: isset($data['numero_comprobante']) ? (int)$data['numero_comprobante'] : null,
            cae: $data['cae'] ?? null,
            caeFecavto: isset($data['cae_fecavto']) ? new \DateTime($data['cae_fecavto']) : null,
            estado: $data['estado'] ?? 'PENDING',
            total: (float)($data['total'] ?? 0),
            moneda: $data['moneda'] ?? 'PES',
            cotizacion: (float)($data['cotizacion'] ?? 1.0),
            observaciones: $data['observaciones'] ?? null,
            xmlRequest: $data['xml_request'] ?? null,
            xmlResponse: $data['xml_response'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null,
        );
    }

    /**
     * Convierte el DTO a array para guardar en base de datos
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'customer_id' => $this->customerId,
            'tipo_comprobante' => $this->tipoComprobante,
            'punto_venta' => $this->puntoVenta,
            'numero_comprobante' => $this->numeroComprobante,
            'cae' => $this->cae,
            'cae_fecavto' => $this->caeFecavto?->format('Y-m-d H:i:s'),
            'estado' => $this->estado,
            'total' => $this->total,
            'moneda' => $this->moneda,
            'cotizacion' => $this->cotizacion,
            'observaciones' => $this->observaciones,
            'xml_request' => $this->xmlRequest,
            'xml_response' => $this->xmlResponse,
        ];
    }

    /**
     * Valida el DTO antes de enviar a ARCA
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->companyId === null) {
            $errors[] = 'La empresa es requerida';
        }

        if ($this->tipoComprobante === 0) {
            $errors[] = 'El tipo de comprobante es requerido';
        } elseif (!in_array($this->tipoComprobante, [1, 2, 3, 6, 7, 8])) {
            $errors[] = 'Tipo de comprobante inválido';
        }

        if ($this->puntoVenta === 0 || $this->puntoVenta > 9999) {
            $errors[] = 'El punto de venta debe estar entre 1 y 9999';
        }

        if ($this->total <= 0) {
            $errors[] = 'El total debe ser mayor a 0';
        }

        if (empty($this->clienteCuit)) {
            $errors[] = 'El CUIT del cliente es requerido';
        }

        if (empty($this->clienteRazonSocial)) {
            $errors[] = 'La razón social del cliente es requerida';
        }

        return $errors;
    }

    /**
     * Verifica si es una factura (A o B)
     */
    public function isFactura(): bool
    {
        return in_array($this->tipoComprobante, [self::TIPO_FACTURA_A, self::TIPO_FACTURA_B]);
    }

    /**
     * Verifica si es una nota de crédito
     */
    public function isNotaCredito(): bool
    {
        return in_array($this->tipoComprobante, [self::TIPO_NOTA_CREDITO_A, self::TIPO_NOTA_CREDITO_B]);
    }

    /**
     * Verifica si es una nota de débito
     */
    public function isNotaDebito(): bool
    {
        return in_array($this->tipoComprobante, [self::TIPO_NOTA_DEBITO_A, self::TIPO_NOTA_DEBITO_B]);
    }
}
