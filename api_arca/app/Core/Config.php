<?php

namespace ApiArca\App\Core;

/**
 * Clase Config para manejar la configuración de la aplicación
 */
class Config
{
    private static array $configs = [];

    /**
     * Obtiene un valor de configuración
     * 
     * @param string $key Clave en formato dot notation (ej: 'app.name')
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        // Cargar configuración si no está cargada
        if (!isset(self::$configs[$file])) {
            self::$configs[$file] = self::loadConfig($file);
        }

        // Navegar por el array de configuración
        $value = self::$configs[$file];
        
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Establece un valor de configuración en tiempo de ejecución
     * 
     * @param string $key Clave en formato dot notation
     * @param mixed $value Valor a establecer
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        // Cargar configuración si no está cargada
        if (!isset(self::$configs[$file])) {
            self::$configs[$file] = self::loadConfig($file);
        }

        // Navegar y establecer el valor
        $config = &self::$configs[$file];
        
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $config[$part] = $value;
            } else {
                if (!isset($config[$part]) || !is_array($config[$part])) {
                    $config[$part] = [];
                }
                $config = &$config[$part];
            }
        }
    }

    /**
     * Carga un archivo de configuración
     * 
     * @param string $file Nombre del archivo sin extensión
     * @return array
     */
    private static function loadConfig(string $file): array
    {
        $path = __DIR__ . "/../../config/{$file}.php";

        if (!file_exists($path)) {
            return [];
        }

        return require $path;
    }

    /**
     * Verifica si existe una clave de configuración
     * 
     * @param string $key Clave en formato dot notation
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Obtiene toda la configuración de un archivo
     * 
     * @param string $file Nombre del archivo sin extensión
     * @return array
     */
    public static function all(string $file): array
    {
        if (!isset(self::$configs[$file])) {
            self::$configs[$file] = self::loadConfig($file);
        }

        return self::$configs[$file];
    }

    /**
     * Limpia el cache de configuración
     * 
     * @return void
     */
    public static function flush(): void
    {
        self::$configs = [];
    }
}
