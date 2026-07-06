<?php

namespace App\Services;

use App\Models\OdooConfigModel;
use App\Models\CatalogModel;
use App\Models\CatalogItemModel;

class CatalogSyncService
{
    private static array $catalogDefinitions = [
        ['name' => 'priority', 'model' => 'project.task', 'field' => 'priority'],
        ['name' => 'users', 'model' => 'res.users', 'field' => ''],
        ['name' => 'employees', 'model' => 'hr.employee', 'field' => ''],
    ];

    public static function syncCatalogs(string $odooConfigId): void
    {
        $configModel = new OdooConfigModel();
        $config = $configModel->find($odooConfigId);
        if (!$config) {
            throw new \RuntimeException('OdooConfig not found');
        }

        $odoo = new OdooService([
            'url' => $config->url,
            'dbName' => $config->dbName,
            'username' => $config->username,
            'apiKey' => $config->apiKey,
        ]);

        $odoo->authenticate();

        foreach (self::$catalogDefinitions as $def) {
            try {
                if ($def['name'] === 'users') {
                    self::syncUserCatalog($odoo, $odooConfigId);
                } elseif ($def['name'] === 'employees') {
                    self::syncEmployeeCatalog($odoo, $odooConfigId);
                } else {
                    self::syncSelectionCatalog($odoo, $odooConfigId, $def);
                }
                log_message('info', "[CatalogSync] ✅ {$def['name']}");
            } catch (\Exception $err) {
                log_message('error', "[CatalogSync] ❌ {$def['name']}: {$err->getMessage()}");
            }
        }
    }

    private static function syncSelectionCatalog(OdooService $odoo, string $odooConfigId, array $def): void
    {
        $items = $odoo->fetchFieldSelection($def['model'], $def['field']);
        $catalogModel = new CatalogModel();
        $catalog = $catalogModel->upsert($def['name'], $odooConfigId, [
            'lastSyncAt' => date('Y-m-d H:i:s'),
        ]);

        $itemModel = new CatalogItemModel();
        $keys = [];
        foreach ($items as $item) {
            $itemModel->upsert($catalog->id, $item['key'], [
                'value' => $item['value'],
            ]);
            $keys[] = $item['key'];
        }
        $itemModel->deleteByCatalogExcept($catalog->id, $keys);
    }

    private static function syncUserCatalog(OdooService $odoo, string $odooConfigId): void
    {
        $users = $odoo->fetchUsers();
        $catalogModel = new CatalogModel();
        $catalog = $catalogModel->upsert('users', $odooConfigId, [
            'lastSyncAt' => date('Y-m-d H:i:s'),
        ]);

        $itemModel = new CatalogItemModel();
        $ids = [];
        foreach ($users as $user) {
            $key = (string) $user['id'];
            $itemModel->upsert($catalog->id, $key, [
                'value' => $user['name'],
                'extra' => json_encode(['email' => $user['email']]),
            ]);
            $ids[] = $key;
        }
        $itemModel->deleteByCatalogExcept($catalog->id, $ids);
    }

    private static function syncEmployeeCatalog(OdooService $odoo, string $odooConfigId): void
    {
        $employees = $odoo->fetchEmployees();
        $catalogModel = new CatalogModel();
        $catalog = $catalogModel->upsert('employees', $odooConfigId, [
            'lastSyncAt' => date('Y-m-d H:i:s'),
        ]);

        $itemModel = new CatalogItemModel();
        $ids = [];
        foreach ($employees as $emp) {
            $key = (string) $emp['id'];
            $extra = [];
            if ($emp['userId'] !== null) {
                $extra['userId'] = $emp['userId'];
            }
            $itemModel->upsert($catalog->id, $key, [
                'value' => $emp['name'],
                'extra' => !empty($extra) ? json_encode($extra) : null,
            ]);
            $ids[] = $key;
        }
        $itemModel->deleteByCatalogExcept($catalog->id, $ids);
    }
}
