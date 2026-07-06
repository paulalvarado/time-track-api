<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public array $allowedOrigins = ['http://localhost:5173', 'http://localhost:5172'];
    public array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'];
    public array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'];
    public bool $allowCredentials = true;
    public int $maxAge = 86400;
}
