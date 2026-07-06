<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\OdooConfigModel;
use App\Services\CatalogSyncService;

class SyncCatalogs extends BaseCommand
{
    protected $group = 'timetrack';
    protected $name = 'sync:catalogs';
    protected $description = 'Sincroniza los catálogos (prioridades, usuarios, empleados) desde Odoo.';
    protected $usage = 'sync:catalogs';
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
            CLI::write("Sincronizando catálogos para config {$config->id}...", 'cyan');
            try {
                CatalogSyncService::syncCatalogs($config->id);
                CLI::write("  ✅ OK", 'green');
            } catch (\Exception $e) {
                CLI::write("  ❌ Error: {$e->getMessage()}", 'red');
            }
        }

        CLI::write('SyncCatalogs completado.', 'green');
    }
}
