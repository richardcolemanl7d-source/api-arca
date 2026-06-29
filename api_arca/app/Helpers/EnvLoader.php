<?php

namespace ApiArca\App\Helpers;

/**
 * Helper para cargar variables de entorno desde archivo .env
 */
class EnvLoader
{
    /**
     * Carga las variables de entorno desde el archivo .env
     * 
     * @param string $path Ruta al archivo .env
     * @return void
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parsear línea KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover comillas si existen
                if (self::isQuoted($value)) {
                    $value = substr($value, 1, -1);
                }

                // Establecer variable de entorno si no existe
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * Verifica si un valor está entre comillas
     * 
     * @param string $value
     * @return bool
     */
    private static function isQuoted(string $value): bool
    {
        return (substr($value, 0, 1) === '"' && substr($value, -1) === '"')
            || (substr($value, 0, 1) === "'" && substr($value, -1) === "'");
    }
}

/**
 * Función helper para obtener variables de entorno
 * 
 * @param string $key Clave de la variable
 * @param mixed $default Valor por defecto
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }

    // Convertir valores booleanos y null
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
        case 'none':
        case '(none)':
            return null;
    }

    return $value;
}
