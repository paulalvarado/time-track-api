<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public array $default = [
        'allowedOrigins'         => ['http://localhost:5173', 'http://localhost:5172', 'https://time-track-app.paulperez.dev'],
        'allowedOriginsPatterns' => [],
        'supportsCredentials'    => true,
        'allowedHeaders'         => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
        'allowedMethods'         => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
        'maxAge'                 => 86400,
    ];
}
