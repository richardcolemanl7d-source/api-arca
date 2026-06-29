<?php

namespace ApiArca\App\Crypto;

/**
 * Servicio de encriptación AES-256-GCM para certificados y claves privadas
 */
class AESService
{
    private string $masterKey;
    private string $cipher;
    private int $ivLength;
    private int $tagLength;

    /**
     * Constructor
     * 
     * @param string|null $masterKey Clave maestra de encriptación (hex)
     */
    public function __construct(?string $masterKey = null)
    {
        $this->masterKey = $masterKey ?? env('ARCA_MASTER_KEY');
        
        if (empty($this->masterKey)) {
            throw new \RuntimeException('ARCA_MASTER_KEY no configurada');
        }

        // Validar longitud de la clave (64 caracteres hex = 32 bytes para AES-256)
        if (strlen($this->masterKey) !== 64) {
            throw new \RuntimeException(
                'ARCA_MASTER_KEY debe tener 64 caracteres hexadecimales (32 bytes)'
            );
        }

        $this->cipher = 'aes-256-gcm';
        $this->ivLength = 12; // 96 bits recomendado para GCM
        $this->tagLength = 16; // 128 bits
    }

    /**
     * Encripta datos usando AES-256-GCM
     * 
     * @param string $data Datos a encriptar
     * @return array ['encrypted' => string, 'iv' => string, 'tag' => string]
     * @throws \RuntimeException Si falla la encriptación
     */
    public function encrypt(string $data): array
    {
        // Generar IV aleatorio
        $iv = openssl_random_pseudo_bytes($this->ivLength);
        
        if ($iv === false) {
            throw new \RuntimeException('Error generando IV aleatorio');
        }

        // Encriptar datos
        $tag = '';
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            hex2bin($this->masterKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Error encriptando datos: ' . openssl_error_string());
        }

        return [
            'encrypted' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
        ];
    }

    /**
     * Desencripta datos usando AES-256-GCM
     * 
     * @param string $encrypted Datos encriptados (base64)
     * @param string $iv Vector de inicialización (base64)
     * @param string $tag Tag de autenticación (base64)
     * @return string Datos desencriptados
     * @throws \RuntimeException Si falla la desencriptación
     */
    public function decrypt(string $encrypted, string $iv, string $tag): string
    {
        $decodedEncrypted = base64_decode($encrypted);
        $decodedIv = base64_decode($iv);
        $decodedTag = base64_decode($tag);

        if ($decodedEncrypted === false || $decodedIv === false || $decodedTag === false) {
            throw new \RuntimeException('Error decodificando datos encriptados');
        }

        // Desencriptar datos
        $decrypted = openssl_decrypt(
            $decodedEncrypted,
            $this->cipher,
            hex2bin($this->masterKey),
            OPENSSL_RAW_DATA,
            $decodedIv,
            $decodedTag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Error desencriptando datos: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Encripta un certificado PEM
     * 
     * @param string $certificate Contenido del certificado PEM
     * @return array Datos encriptados listos para guardar en BD
     */
    public function encryptCertificate(string $certificate): array
    {
        return $this->encrypt($certificate);
    }

    /**
     * Desencripta un certificado PEM
     * 
     * @param string $encrypted Certificado encriptado (base64)
     * @param string $iv IV encriptado (base64)
     * @param string $tag Tag encriptado (base64)
     * @return string Contenido del certificado PEM
     */
    public function decryptCertificate(string $encrypted, string $iv, string $tag): string
    {
        return $this->decrypt($encrypted, $iv, $tag);
    }

    /**
     * Encripta una clave privada PEM
     * 
     * @param string $privateKey Contenido de la clave privada PEM
     * @return array Datos encriptados listos para guardar en BD
     */
    public function encryptPrivateKey(string $privateKey): array
    {
        return $this->encrypt($privateKey);
    }

    /**
     * Desencripta una clave privada PEM
     * 
     * @param string $encrypted Clave privada encriptada (base64)
     * @param string $iv IV encriptado (base64)
     * @param string $tag Tag encriptado (base64)
     * @return string Contenido de la clave privada PEM
     */
    public function decryptPrivateKey(string $encrypted, string $iv, string $tag): string
    {
        return $this->decrypt($encrypted, $iv, $tag);
    }

    /**
     * Genera una nueva clave maestra aleatoria
     * 
     * @return string Clave maestra en formato hex (64 caracteres)
     */
    public static function generateMasterKey(): string
    {
        $key = openssl_random_pseudo_bytes(32);
        
        if ($key === false) {
            throw new \RuntimeException('Error generando clave maestra');
        }

        return bin2hex($key);
    }

    /**
     * Obtiene el cipher utilizado
     * 
     * @return string
     */
    public function getCipher(): string
    {
        return $this->cipher;
    }

    /**
     * Obtiene la longitud del IV
     * 
     * @return int
     */
    public function getIvLength(): int
    {
        return $this->ivLength;
    }
}
