<?php

namespace Config;

use CodeIgniter\Cache\Handlers\DummyHandler;
use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Config\BaseConfig;

class Cache extends BaseConfig
{
    public string $handler = 'file';
    public string $backupHandler = 'dummy';
    public string $prefix = '';
    public int $ttl = 60;

    /**
     * @var array<string, string>
     */
    public $validHandlers = [
        'dummy' => DummyHandler::class,
        'file'  => FileHandler::class,
    ];

    public string $reservedCharacters = '{}()/\@:';
    public string $filePath = WRITEPATH . 'cache';

    /** @var array{storePath?: string, mode?: int} */
    public array $file = [
        'storePath' => WRITEPATH . 'cache/',
        'mode'      => 0640,
    ];
    public string $memcachedHost = '127.0.0.1';
    public int $memcachedPort = 11211;
    public int $memcachedWeight = 1;
    public string $redisHost = '127.0.0.1';
    public int $redisPort = 6379;
    public string $redisPassword = '';
    public int $redisDatabase = 0;
    public int $redisTimeOut = 60;
    public bool $redisEnabled = false;
    public bool $cacheQueryString = false;
}
