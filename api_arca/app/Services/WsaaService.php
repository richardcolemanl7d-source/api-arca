<?php

namespace ApiArca\App\Services;

use ApiArca\App\Helpers\Logger;

/**
 * Servicio WSAA - Web Services Authentication & Authorization
 * Maneja la autenticación con ARCA/AFIP para obtener tokens de acceso
 */
class WsaaService
{
    private CertificateService $certificateService;
    private Logger $logger;
    private string $wsaaUrl;
    private int $timeout;

    public function __construct(
        CertificateService $certificateService,
        Logger $logger
    ) {
        $this->certificateService = $certificateService;
        $this->logger = $logger;
        
        $ambiente = env('ARCA_AMBIENTE', 'homologacion');
        $this->wsaaUrl = $ambiente === 'production' 
            ? 'https://wsaa.arca.gob.ar/ws/services/LoginCms'
            : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
        $this->timeout = 30;
    }

    /**
     * Genera el TRA (Ticket de Request de Acceso)
     * 
     * @param int $companyId ID de la empresa
     * @param string $service Servicio a solicitar (ej: wsfe)
     * @return string XML del TRA
     */
    public function generarTRA(int $companyId, string $service): string
    {
        $now = new \DateTime();
        $future = (clone $now)->modify('+12 hours');

        $tra = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $tra .= '<loginTicketRequest version="1.0">' . PHP_EOL;
        $tra .= '  <header>' . PHP_EOL;
        $tra .= '    <uniqueId>' . $now->getTimestamp() . '</uniqueId>' . PHP_EOL;
        $tra .= '    <generationTime>' . $now->format('Y-m-d\TH:i:sP') . '</generationTime>' . PHP_EOL;
        $tra .= '    <expirationTime>' . $future->format('Y-m-d\TH:i:sP') . '</expirationTime>' . PHP_EOL;
        $tra .= '  </header>' . PHP_EOL;
        $tra .= '  <service>' . $service . '</service>' . PHP_EOL;
        $tra .= '</loginTicketRequest>';

        return $tra;
    }

    /**
     * Firma el TRA usando el certificado de la empresa
     * 
     * @param int $companyId ID de la empresa
     * @param string $tra XML del TRA
     * @return string CMS firmado en base64
     */
    public function firmarCMS(int $companyId, string $tra): string
    {
        try {
            $certificate = $this->certificateService->decryptCertificate($companyId);
            $privateKey = $this->certificateService->decryptPrivateKey($companyId);

            // Firmar el TRA
            $signedFile = tempnam(sys_get_temp_dir(), 'signed_');
            $traFile = tempnam(sys_get_temp_dir(), 'tra_');

            file_put_contents($traFile, $tra);

            $result = openssl_pkcs7_sign(
                $traFile,
                $signedFile,
                $certificate,
                $privateKey,
                [],
                PKCS7_BINARY | PKCS7_NOATTR
            );

            unlink($traFile);

            if (!$result) {
                throw new \RuntimeException('Error firmando CMS: ' . openssl_error_string());
            }

            // Extraer solo el contenido firmado (sin headers)
            $signedContent = file_get_contents($signedFile);
            unlink($signedFile);

            // Parsear para extraer el body del CMS
            if (preg_match('/^(.+?)\s*$/ms', $signedContent, $matches)) {
                // Si tiene headers MIME, extraer el body
                $parts = explode("\n\n", $signedContent, 2);
                $cmsContent = $parts[1] ?? $signedContent;
            } else {
                $cmsContent = $signedContent;
            }

            return base64_encode($cmsContent);
        } catch (\Exception $e) {
            $this->logger->error('Error firmando CMS', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error firmando CMS: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Realiza el login al CM (Central de Autenticación)
     * 
     * @param int $companyId ID de la empresa
     * @param string $service Servicio a solicitar
     * @return array ['access_token' => string, 'sign_token' => string, 'expiration' => DateTime]
     */
    public function loginCms(int $companyId, string $service): array
    {
        try {
            // Generar y firmar TRA
            $tra = $this->generarTRA($companyId, $service);
            $cms = $this->firmarCMS($companyId, $tra);

            // Crear cliente SOAP
            $client = new \SoapClient($this->wsaaUrl . '?WSDL', [
                'soap_version' => SOAP_1_2,
                'trace' => true,
                'connection_timeout' => $this->timeout,
            ]);

            // Llamar al servicio
            $result = $client->loginCms(['in0' => $cms]);

            // Parsear respuesta XML
            $credentials = simplexml_load_string($result->loginCmsReturn);

            if ($credentials === false) {
                throw new \RuntimeException('Error parseando respuesta de WSAA');
            }

            $accessToken = (string)$credentials->credentials->token;
            $signToken = (string)$credentials->credentials->sign;
            $expiration = new \DateTime((string)$credentials->header->expirationTime);

            $this->logger->info('Login WSAA exitoso', [
                'company_id' => $companyId,
                'service' => $service,
                'expiration' => $expiration->format('Y-m-d H:i:s')
            ]);

            return [
                'access_token' => $accessToken,
                'sign_token' => $signToken,
                'expiration' => $expiration,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error en login WSAA', [
                'company_id' => $companyId,
                'service' => $service,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error en login WSAA: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Obtiene un token de acceso (combina todos los pasos)
     * 
     * @param int $companyId ID de la empresa
     * @param string $service Servicio a solicitar
     * @return array Tokens de acceso
     */
    public function obtenerToken(int $companyId, string $service): array
    {
        return $this->loginCms($companyId, $service);
    }
}
