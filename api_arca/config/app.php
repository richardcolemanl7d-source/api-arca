<?php
/**
 * Configuración principal de la aplicación
 */

return [
    'name' => 'API ARCA Gateway',
    'version' => '1.0.0',
    'debug' => env('APP_DEBUG', false),
    'timezone' => 'America/Argentina/Buenos_Aires',
    
    // Configuración de ARCA/AFIP
    'arca' => [
        'wsaa_url_production' => 'https://wsaa.arca.gob.ar/ws/services/LoginCms',
        'wsaa_url_homologacion' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
        'wsfe_url_production' => 'https://servicios1.arca.gob.ar/servicio/api/sr-padron/v2/persona/',
        'wsfe_url_homologacion' => 'https://fwshomo.afip.gov.ar/ws/service/service.asmx?WSDL',
        'ambiente' => env('ARCA_AMBIENTE', 'homologacion'),
        'timeout' => 30,
    ],
    
    // Seguridad
    'security' => [
        'master_key' => env('ARCA_MASTER_KEY'),
        'cipher' => 'aes-256-gcm',
        'iv_length' => 12,
        'tag_length' => 16,
    ],
    
    // Rate limiting
    'rate_limit' => [
        'enabled' => true,
        'max_requests' => 100,
        'period_seconds' => 60,
        'driver' => 'redis', // redis, database, memory
    ],
    
    // Logging
    'logging' => [
        'path' => __DIR__ . '/../storage/logs',
        'level' => env('LOG_LEVEL', 'info'),
        'channels' => ['daily', 'error', 'audit'],
    ],
    
    // Cache
    'cache' => [
        'driver' => 'redis', // redis, file, memory
        'path' => __DIR__ . '/../storage/cache',
        'prefix' => 'api_arca_',
    ],
];
