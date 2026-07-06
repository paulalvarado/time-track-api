<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Auth extends BaseConfig
{
    public string $jwtSecret = 'dev-secret-change-in-production';
    public string $jwtAlgorithm = 'HS256';
    public string $sessionExpiresIn = 'P7D';
    public int $sessionExpiresSeconds = 604800;
}
