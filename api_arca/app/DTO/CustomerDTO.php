<?php

namespace ApiArca\App\DTO;

/**
 * Data Transfer Object para Customer
 */
class CustomerDTO
{
    // Tipos de documentos según AFIP
    public const TIPO_CUIT = 96;
    public const TIPO_DNI = 96;
    public const TIPO_PASAPORTE = 94;
    public const TIPO_CI_EXTRANJERA = 95;

    // Condiciones de IVA
    public const COND_RESPONSABLE_INSCRIPTO = 1;
    public const COND_MONOTRIBUTISTA = 2;
    public const COND_EXENTO = 3;
    public const COND_NO_RESPONSABLE = 4;
    public const COND_CONSUMIDOR_FINAL = 5;
    public const COND_RESPONSABLE_NO_INSCRIPTO = 6;

    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $companyId = null,
        public readonly string $cuit = '',
        public readonly string $razonSocial = '',
        public readonly int $tipoDocumento = self::TIPO_CUIT,
        public readonly int $condicionIva = self::COND_CONSUMIDOR_FINAL,
        public readonly ?string $domicilio = null,
        public readonly ?string $localidad = null,
        public readonly ?string $provincia = null,
        public readonly ?string $codigoPostal = null,
        public readonly int $pais = 10, // Argentina
        public readonly ?string $email = null,
        public readonly ?string $telefono = null,
        public readonly bool $activo = true,
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
            companyId: isset($data['company_id']) ? (int)$data['company_id'] : null,
            cuit: $data['cuit'] ?? '',
            razonSocial: $data['razon_social'] ?? '',
            tipoDocumento: (int)($data['tipo_documento'] ?? self::TIPO_CUIT),
            condicionIva: (int)($data['condicion_iva'] ?? self::COND_CONSUMIDOR_FINAL),
            domicilio: $data['domicilio'] ?? null,
            localidad: $data['localidad'] ?? null,
            provincia: $data['provincia'] ?? null,
            codigoPostal: $data['codigo_postal'] ?? null,
            pais: (int)($data['pais'] ?? 10),
            email: $data['email'] ?? null,
            telefono: $data['telefono'] ?? null,
            activo: (bool)($data['activo'] ?? true),
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
            'cuit' => $this->cuit,
            'razon_social' => $this->razonSocial,
            'tipo_documento' => $this->tipoDocumento,
            'condicion_iva' => $this->condicionIva,
            'domicilio' => $this->domicilio,
            'localidad' => $this->localidad,
            'provincia' => $this->provincia,
            'codigo_postal' => $this->codigoPostal,
            'pais' => $this->pais,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'activo' => $this->activo ? 1 : 0,
        ];
    }

    /**
     * Valida el DTO
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->cuit)) {
            $errors[] = 'El CUIT/CUIL es requerido';
        } else {
            $cuitLimpio = preg_replace('/[^0-9]/', '', $this->cuit);
            if (strlen($cuitLimpio) !== 11) {
                $errors[] = 'El CUIT/CUIL debe tener 11 dígitos';
            }
        }

        if (empty($this->razonSocial)) {
            $errors[] = 'La razón social es requerida';
        }

        if (!in_array($this->tipoDocumento, [self::TIPO_CUIT, self::TIPO_DNI, self::TIPO_PASAPORTE, self::TIPO_CI_EXTRANJERA])) {
            $errors[] = 'Tipo de documento inválido';
        }

        if (!in_array($this->condicionIva, range(self::COND_RESPONSABLE_INSCRIPTO, self::COND_RESPONSABLE_NO_INSCRIPTO))) {
            $errors[] = 'Condición de IVA inválida';
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }

        return $errors;
    }

    /**
     * Obtiene la descripción del tipo de documento
     */
    public function getTipoDocumentoDesc(): string
    {
        $tipos = [
            self::TIPO_CUIT => 'CUIT',
            self::TIPO_DNI => 'DNI',
            self::TIPO_PASAPORTE => 'Pasaporte',
            self::TIPO_CI_EXTRANJERA => 'CI Extranjera',
        ];

        return $tipos[$this->tipoDocumento] ?? 'Desconocido';
    }

    /**
     * Obtiene la descripción de la condición de IVA
     */
    public function getCondicionIvaDesc(): string
    {
        $condiciones = [
            self::COND_RESPONSABLE_INSCRIPTO => 'Responsable Inscripto',
            self::COND_MONOTRIBUTISTA => 'Monotributista',
            self::COND_EXENTO => 'Exento',
            self::COND_NO_RESPONSABLE => 'No Responsable',
            self::COND_CONSUMIDOR_FINAL => 'Consumidor Final',
            self::COND_RESPONSABLE_NO_INSCRIPTO => 'Responsable No Inscripto',
        ];

        return $condiciones[$this->condicionIva] ?? 'Desconocido';
    }
}
