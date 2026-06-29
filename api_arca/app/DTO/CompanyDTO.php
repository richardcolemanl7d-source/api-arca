<?php

namespace ApiArca\App\DTO;

/**
 * Data Transfer Object para Company
 */
class CompanyDTO
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly string $cuit = '',
        public readonly string $razonSocial = '',
        public readonly ?string $certificadoEncriptado = null,
        public readonly ?string $privateKeyEncriptada = null,
        public readonly ?string $iv = null,
        public readonly ?string $tag = null,
        public readonly bool $activo = true,
        public readonly ?\DateTime $fechaVencimientoCertificado = null,
        public readonly ?\DateTime $createdAt = null,
        public readonly ?\DateTime $updatedAt = null,
    ) {}

    /**
     * Crea un DTO desde un array de base de datos
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            cuit: $data['cuit'] ?? '',
            razonSocial: $data['razon_social'] ?? '',
            certificadoEncriptado: $data['certificado_encriptado'] ?? null,
            privateKeyEncriptada: $data['private_key_encriptada'] ?? null,
            iv: $data['iv'] ?? null,
            tag: $data['tag'] ?? null,
            activo: (bool)($data['activo'] ?? true),
            fechaVencimientoCertificado: isset($data['fecha_vencimiento_certificado']) 
                ? new \DateTime($data['fecha_vencimiento_certificado']) 
                : null,
            createdAt: isset($data['created_at']) 
                ? new \DateTime($data['created_at']) 
                : null,
            updatedAt: isset($data['updated_at']) 
                ? new \DateTime($data['updated_at']) 
                : null,
        );
    }

    /**
     * Convierte el DTO a array para guardar en base de datos
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'cuit' => $this->cuit,
            'razon_social' => $this->razonSocial,
            'certificado_encriptado' => $this->certificadoEncriptado,
            'private_key_encriptada' => $this->privateKeyEncriptada,
            'iv' => $this->iv,
            'tag' => $this->tag,
            'activo' => $this->activo ? 1 : 0,
            'fecha_vencimiento_certificado' => $this->fechaVencimientoCertificado?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Valida el DTO
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->cuit)) {
            $errors[] = 'El CUIT es requerido';
        } elseif (strlen(preg_replace('/[^0-9]/', '', $this->cuit)) !== 11) {
            $errors[] = 'El CUIT debe tener 11 dígitos';
        }

        if (empty($this->razonSocial)) {
            $errors[] = 'La razón social es requerida';
        }

        return $errors;
    }
}
