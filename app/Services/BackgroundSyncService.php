<?php

namespace App\Services;

use App\Models\SyncProgressModel;

class BackgroundSyncService
{
    /**
     * Dispara la sincronización en segundo plano para un usuario.
     * Crea un registro de progreso y ejecuta php spark sync:account en background.
     */
    public static function trigger(string $userId): object
    {
        $progressModel = new SyncProgressModel();

        // Marcar progresos anteriores como obsoletos
        $db = \Config\Database::connect();
        $db->query('UPDATE public.syncprogress SET status = \'obsolete\' WHERE "userId" = ? AND status = \'running\'', [$userId]);

        // Crear nuevo registro de progreso
        $progress = $progressModel->createProgress($userId);

        // Ruta base de la app (asumiendo que estamos en api/)
        $appDir = ROOTPATH;
        $logFile = WRITEPATH . 'logs/sync_' . $progress->id . '.log';

        // Ejecutar en background: nohup php spark sync:account [userId] [progressId] > log 2>&1 &
        $cmd = "nohup php {$appDir}spark sync:account {$userId} {$progress->id} > {$logFile} 2>&1 &";
        exec($cmd);

        return $progress;
    }
}
