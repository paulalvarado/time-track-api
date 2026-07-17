<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\OdooConfigModel;
use App\Services\CatalogSyncService;
use App\Services\SyncService;

class SyncAccount extends BaseCommand
{
    protected $group = 'timetrack';
    protected $name = 'sync:account';
    protected $description = 'Sincroniza catálogos y datos completos desde Odoo para una cuenta específica (por userId).';
    protected $usage = 'sync:account [userId]';
    protected $arguments = [
        'userId' => 'ID del usuario de TimeTrack para el cual sincronizar datos desde Odoo',
    ];

    public function run(array $params)
    {
        $userId = $params[0] ?? null;
        if (!$userId) {
            CLI::write('Debes proporcionar un userId.', 'red');
            CLI::write('Uso: php spark sync:account [userId]', 'yellow');
            return;
        }

        // ──────────────────────────────────────────────
        // 1. Obtener configuración Odoo para este usuario
        // ──────────────────────────────────────────────
        CLI::write("Buscando configuración Odoo para userId: {$userId}...", 'cyan');
        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);

        if (!$config) {
            CLI::write("No se encontró configuración Odoo para el usuario {$userId}.", 'red');
            return;
        }

        CLI::write("Configuración Odoo encontrada: {$config->id}", 'green');
        CLI::write("  URL: {$config->url}");
        CLI::write("  DB:   {$config->dbName}");
        CLI::write("  User: {$config->username}");

        // ──────────────────────────────────────────────
        // 2. Sincronizar catálogos (usuarios, empleados, prioridades)
        // ──────────────────────────────────────────────
        CLI::write("\n═══════════════════════════════════════════", 'cyan');
        CLI::write("  FASE 1/2 — Sincronizando catálogos...", 'cyan');
        CLI::write("═══════════════════════════════════════════\n", 'cyan');
        try {
            CatalogSyncService::syncCatalogs($config->id);
            CLI::write("  ✅ Catálogos sincronizados correctamente", 'green');
        } catch (\Exception $e) {
            CLI::write("  ⚠️  Error en catálogos: {$e->getMessage()}", 'yellow');
            CLI::write("  ⚠️  Continuando con sincronización de datos...", 'yellow');
        }

        // ──────────────────────────────────────────────
        // 3. Sincronizar datos completos (proyectos, tareas, timesheets)
        // ──────────────────────────────────────────────
        CLI::write("\n═══════════════════════════════════════════", 'cyan');
        CLI::write("  FASE 2/2 — Sincronizando datos completos...", 'cyan');
        CLI::write("═══════════════════════════════════════════\n", 'cyan');
        try {
            SyncService::syncAll($config->id);
            CLI::write("  ✅ Datos sincronizados correctamente", 'green');
        } catch (\Exception $e) {
            CLI::write("  ❌ Error en sincronización de datos: {$e->getMessage()}", 'red');
            return;
        }

        CLI::write("\n✅ SyncAccount completado exitosamente.", 'green');
    }
}
