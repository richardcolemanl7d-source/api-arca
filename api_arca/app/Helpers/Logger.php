<?php

namespace ApiArca\App\Helpers;

/**
 * Logger estructurado para la aplicación
 * Implementa logging en archivos con niveles y rotación diaria
 */
class Logger
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_CRITICAL = 'critical';
    public const LEVEL_AUDIT = 'audit';

    private string $path;
    private string $level;
    private array $channels = [];

    /**
     * Constructor
     * 
     * @param string $path Ruta al directorio de logs
     * @param string $level Nivel mínimo de log
     */
    public function __construct(
        string $path,
        string $level = self::LEVEL_INFO
    ) {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
        $this->level = $level;
        
        // Crear directorio si no existe
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Registrar un mensaje de log
     * 
     * @param string $level Nivel del log
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $this->formatContext($context),
        ];

        $logLine = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $filename = $this->getFilename($level);
        file_put_contents($filename, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log de nivel debug
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log de nivel info
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log de nivel warning
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log de nivel error
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log de nivel critical
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log de auditoría
     */
    public function audit(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_AUDIT, $message, $context);
    }

    /**
     * Verifica si se debe registrar el log según el nivel configurado
     * 
     * @param string $level
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        $levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4,
            self::LEVEL_AUDIT => 5,
        ];

        return ($levels[$level] ?? 0) >= ($levels[$this->level] ?? 1);
    }

    /**
     * Obtiene el nombre del archivo de log según el nivel
     * 
     * @param string $level
     * @return string
     */
    private function getFilename(string $level): string
    {
        $date = date('Y-m-d');
        
        // Logs de auditoría van a archivo separado
        if ($level === self::LEVEL_AUDIT) {
            return "{$this->path}/audit-{$date}.log";
        }

        // Logs de error van a archivo separado
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
            return "{$this->path}/error-{$date}.log";
        }

        // Log general diario
        return "{$this->path}/app-{$date}.log";
    }

    /**
     * Formatea el contexto para incluir en el log
     * 
     * @param array $context
     * @return array
     */
    private function formatContext(array $context): array
    {
        // Remover datos sensibles del contexto
        $sensitive = ['password', 'api_key', 'token', 'private_key', 'secret'];
        
        foreach ($sensitive as $key) {
            if (isset($context[$key])) {
                $context[$key] = '***REDACTED***';
            }
        }

        return $context;
    }
}
