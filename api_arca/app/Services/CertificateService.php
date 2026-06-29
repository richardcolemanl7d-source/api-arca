<?php

namespace ApiArca\App\Services;

use ApiArca\App\Repositories\CompanyRepository;
use ApiArca\App\Crypto\AESService;
use ApiArca\App\Helpers\Logger;

/**
 * Servicio para gestión de certificados digitales ARCA/AFIP
 */
class CertificateService
{
    private CompanyRepository $companyRepository;
    private AESService $aesService;
    private Logger $logger;

    public function __construct(
        CompanyRepository $companyRepository,
        AESService $aesService,
        Logger $logger
    ) {
        $this->companyRepository = $companyRepository;
        $this->aesService = $aesService;
        $this->logger = $logger;
    }

    /**
     * Encripta un certificado y clave privada
     * 
     * @param string $certificate Contenido del certificado PEM
     * @param string $privateKey Contenido de la clave privada PEM
     * @return array Datos encriptados listos para guardar
     */
    public function encrypt(string $certificate, string $privateKey): array
    {
        try {
            $certData = $this->aesService->encryptCertificate($certificate);
            $keyData = $this->aesService->encryptPrivateKey($privateKey);

            return [
                'certificado_encriptado' => $certData['encrypted'],
                'private_key_encriptada' => $keyData['encrypted'],
                'iv' => $certData['iv'], // El IV puede ser el mismo para ambos
                'tag' => $certData['tag'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error encriptando certificado', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Error encriptando certificado: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Desencripta el certificado de una empresa
     * 
     * @param int $companyId ID de la empresa
     * @return string Contenido del certificado PEM
     */
    public function decryptCertificate(int $companyId): string
    {
        $company = $this->companyRepository->findById($companyId);

        if ($company === null) {
            throw new \RuntimeException('Empresa no encontrada', 404);
        }

        try {
            return $this->aesService->decryptCertificate(
                $company['certificado_encriptado'],
                $company['iv'],
                $company['tag']
            );
        } catch (\Exception $e) {
            $this->logger->error('Error desencriptando certificado', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error desencriptando certificado', 500, $e);
        }
    }

    /**
     * Desencripta la clave privada de una empresa
     * 
     * @param int $companyId ID de la empresa
     * @return string Contenido de la clave privada PEM
     */
    public function decryptPrivateKey(int $companyId): string
    {
        $company = $this->companyRepository->findById($companyId);

        if ($company === null) {
            throw new \RuntimeException('Empresa no encontrada', 404);
        }

        try {
            return $this->aesService->decryptPrivateKey(
                $company['private_key_encriptada'],
                $company['iv'],
                $company['tag']
            );
        } catch (\Exception $e) {
            $this->logger->error('Error desencriptando clave privada', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error desencriptando clave privada', 500, $e);
        }
    }

    /**
     * Valida un certificado PEM
     * 
     * @param string $certificate Contenido del certificado PEM
     * @return array ['valid' => bool, 'subject' => string, 'issuer' => string, 'expiration' => DateTime]
     */
    public function validateCertificate(string $certificate): array
    {
        // Escribir certificado temporalmente para validación
        $tempFile = tempnam(sys_get_temp_dir(), 'cert_');
        
        try {
            file_put_contents($tempFile, $certificate);
            
            $certInfo = openssl_x509_parse($certificate);
            
            if ($certInfo === false) {
                return [
                    'valid' => false,
                    'error' => 'No se pudo parsear el certificado',
                ];
            }

            // Verificar vigencia
            $now = time();
            $validFrom = $certInfo['validFrom_time_t'];
            $validTo = $certInfo['validTo_time_t'];

            if ($now < $validFrom) {
                return [
                    'valid' => false,
                    'error' => 'El certificado aún no está vigente',
                ];
            }

            if ($now > $validTo) {
                return [
                    'valid' => false,
                    'error' => 'El certificado ha expirado',
                ];
            }

            // Extraer información relevante
            $subject = is_array($certInfo['subject']) ? $certInfo['subject'] : [];
            $issuer = is_array($certInfo['issuer']) ? $certInfo['issuer'] : [];

            return [
                'valid' => true,
                'subject' => $subject,
                'issuer' => $issuer,
                'expiration' => new \DateTime('@' . $validTo),
                'daysToExpiration' => max(0, (int)(($validTo - $now) / 86400)),
            ];
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Calcula los días restantes hasta el vencimiento del certificado
     * 
     * @param string $certificate Contenido del certificado PEM
     * @return int Días restantes (0 si expiró)
     */
    public function daysToExpiration(string $certificate): int
    {
        $info = $this->validateCertificate($certificate);
        
        return $info['daysToExpiration'] ?? 0;
    }

    /**
     * Verifica si un certificado está próximo a vencer
     * 
     * @param string $certificate Contenido del certificado PEM
     * @param int $thresholdDays Umbral de días para considerar "próximo a vencer"
     * @return bool
     */
    public function isExpiringSoon(string $certificate, int $thresholdDays = 30): bool
    {
        return $this->daysToExpiration($certificate) <= $thresholdDays;
    }

    /**
     * Obtiene el CUIT desde el certificado
     * 
     * @param string $certificate Contenido del certificado PEM
     * @return string|null CUIT o null si no se puede extraer
     */
    public function extractCuitFromCertificate(string $certificate): ?string
    {
        $certInfo = openssl_x509_parse($certificate);
        
        if ($certInfo === false || !isset($certInfo['subject'])) {
            return null;
        }

        // El CUIT suele estar en el campo serialNumber o CN
        $subject = $certInfo['subject'];
        
        if (isset($subject['serialNumber'])) {
            // Formato típico: CUIT 20123456789
            if (preg_match('/CUIT\s*(\d{11})/', $subject['serialNumber'], $matches)) {
                return $matches[1];
            }
        }

        // Intentar extraer del CN
        if (isset($subject['CN'])) {
            if (preg_match('/(\d{11})/', $subject['CN'], $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
