<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Commands extends BaseConfig
{
    public array $commands = [
        'App\Commands\SyncWorker'   => ['group' => 'timetrack', 'name' => 'sync:worker'],
        'App\Commands\SyncCatalogs' => ['group' => 'timetrack', 'name' => 'sync:catalogs'],
    ];

    public array $discovery = [
        'main',
        'models',
        'controllers',
        'commands',
    ];
}
