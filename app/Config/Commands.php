<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Commands extends BaseConfig
{
    public array $commands = [
        'App\Commands\SyncWorker'   => ['group' => 'timetrack', 'name' => 'sync:worker'],
        'App\Commands\SyncCatalogs' => ['group' => 'timetrack', 'name' => 'sync:catalogs'],
        'App\Commands\SyncAccount'  => ['group' => 'timetrack', 'name' => 'sync:account'],
    ];

    public array $discovery = [
        'main',
        'models',
        'controllers',
        'commands',
    ];
}
