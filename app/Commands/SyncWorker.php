<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\OdooConfigModel;
use App\Services\SyncService;

class SyncWorker extends BaseCommand
{
    protected $group = 'timetrack';
    protected $name = 'sync:worker';
    protected $description = 'Sincroniza todos los proyectos, tareas y partes de horas desde Odoo.';
    protected $usage = 'sync:worker';
    protected $arguments = [];

    public function run(array $params)
    {
        $configModel = new OdooConfigModel();
        $db = \Config\Database::connect();
        $builder = $db->table('public."OdooConfig"');
        $configs = $builder->select('id')->get()->getResult();

        if (empty($configs)) {
            CLI::write('No hay configuraciones de Odoo.', 'yellow');
            return;
        }

        foreach ($configs as $config) {
            CLI::write("Sincronizando config {$config->id}...", 'cyan');
            try {
                SyncService::syncAll($config->id);
                CLI::write("  ✅ OK", 'green');
            } catch (\Exception $e) {
                CLI::write("  ❌ Error: {$e->getMessage()}", 'red');
            }
        }

        CLI::write('SyncWorker completado.', 'green');
    }
}
