<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\OdooConfigModel;
use App\Models\SyncProgressModel;
use App\Services\CatalogSyncService;
use App\Services\SyncService;

class SyncAccount extends BaseCommand
{
    protected $group = 'timetrack';
    protected $name = 'sync:account';
    protected $description = 'Sincroniza catálogos y datos completos desde Odoo para una cuenta específica (por userId).';
    protected $usage = 'sync:account [userId] [progressId]';
    protected $arguments = [
        'userId'     => 'ID del usuario de TimeTrack para el cual sincronizar datos desde Odoo',
        'progressId' => '(opcional) ID del registro de progreso para actualizar avance',
    ];

    public function run(array $params)
    {
        $userId = $params[0] ?? null;
        $progressId = $params[1] ?? null;

        if (!$userId) {
            CLI::write('Debes proporcionar un userId.', 'red');
            CLI::write('Uso: php spark sync:account [userId] [progressId]', 'yellow');
            return;
        }

        $progressModel = $progressId ? new SyncProgressModel() : null;

        $this->updateProgress($progressModel, $progressId, 'running', 0, 'Iniciando sincronización...');

        // ──────────────────────────────────────────────
        // 1. Obtener configuración Odoo para este usuario
        // ──────────────────────────────────────────────
        $this->log($progressModel, $progressId, "Buscando configuración Odoo para userId: {$userId}...");
        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);

        if (!$config) {
            $this->log($progressModel, $progressId, "No se encontró configuración Odoo para el usuario {$userId}.");
            $this->updateProgress($progressModel, $progressId, 'error', 0, 'Configuración Odoo no encontrada');
            CLI::write("No se encontró configuración Odoo para el usuario {$userId}.", 'red');
            return;
        }

        $this->log($progressModel, $progressId, "Configuración encontrada: {$config->url} / {$config->dbName}");
        $this->updateProgress($progressModel, $progressId, 'running', 5, 'Configuración Odoo cargada');

        // ──────────────────────────────────────────────
        // 2. Sincronizar catálogos (usuarios, empleados, prioridades)
        // ──────────────────────────────────────────────
        $this->log($progressModel, $progressId, '── FASE 1/2: Sincronizando catálogos ──');
        $this->updateProgress($progressModel, $progressId, 'running', 10, 'Sincronizando catálogos...');

        try {
            CatalogSyncService::syncCatalogs($config->id);
            $this->log($progressModel, $progressId, '✅ Catálogos sincronizados correctamente');
            $this->updateProgress($progressModel, $progressId, 'running', 25, 'Catálogos sincronizados');
        } catch (\Exception $e) {
            $this->log($progressModel, $progressId, "⚠️  Error en catálogos: {$e->getMessage()}");
            $this->log($progressModel, $progressId, '⚠️  Continuando con sincronización de datos...');
        }

        // ──────────────────────────────────────────────
        // 3. Sincronizar datos completos (proyectos, tareas, timesheets)
        // ──────────────────────────────────────────────
        $this->log($progressModel, $progressId, '── FASE 2/2: Sincronizando datos completos ──');
        $this->updateProgress($progressModel, $progressId, 'running', 30, 'Descargando proyectos...');

        try {
            SyncService::syncAll($config->id);
            $this->log($progressModel, $progressId, '✅ Datos sincronizados correctamente');
            $this->updateProgress($progressModel, $progressId, 'running', 90, 'Datos sincronizados');
        } catch (\Exception $e) {
            $this->log($progressModel, $progressId, "❌ Error en sincronización de datos: {$e->getMessage()}");
            $this->updateProgress($progressModel, $progressId, 'error', 0, "Error: {$e->getMessage()}");
            CLI::write("  ❌ Error en sincronización de datos: {$e->getMessage()}", 'red');
            return;
        }

        $this->log($progressModel, $progressId, 'SyncAccount completado exitosamente.');
        $this->updateProgress($progressModel, $progressId, 'completed', 100, 'Sincronización completada');
        CLI::write("\n✅ SyncAccount completado exitosamente.", 'green');
    }

    private function log(?SyncProgressModel $model, ?string $progressId, string $message): void
    {
        CLI::write("  {$message}");
        if ($model && $progressId) {
            $model->appendLog($progressId, $message);
        }
    }

    private function updateProgress(?SyncProgressModel $model, ?string $progressId, string $status, int $progress, string $message): void
    {
        if ($model && $progressId) {
            $model->updateProgress($progressId, [
                'status'   => $status,
                'progress' => $progress,
            ]);
            $model->appendLog($progressId, $message);
        }
    }
}
