<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Services\OdooService;
use App\Services\CatalogSyncService;
use App\Services\SyncService;

class OdooConfigController extends BaseController
{
    public function save()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $url = $data['url'] ?? '';
        $dbName = $data['dbName'] ?? '';
        $username = $data['username'] ?? '';
        $apiKey = $data['apiKey'] ?? '';

        if (!$url || !$dbName || !$username || !$apiKey) {
            return $this->respondError('url, dbName, username, and apiKey are required');
        }

        // Test Odoo connection
        $odoo = new OdooService(['url' => $url, 'dbName' => $dbName, 'username' => $username, 'apiKey' => $apiKey]);
        try {
            $odoo->authenticate();
        } catch (\Exception $e) {
            return $this->respondError('Failed to connect to Odoo with the provided credentials.');
        }

        // Save config
        $configModel = new OdooConfigModel();
        $config = $configModel->upsert($userId, [
            'url' => $url,
            'dbName' => $dbName,
            'username' => $username,
            'apiKey' => $apiKey,
        ]);

        // Sync catalogs (fire and forget)
        try {
            CatalogSyncService::syncCatalogs($config->id);
        } catch (\Exception $e) {
            log_message('error', "[OdooConfig] Catalog sync error: {$e->getMessage()}");
        }

        // Full sync (fire and forget)
        try {
            SyncService::syncAll($config->id);
        } catch (\Exception $e) {
            log_message('error', "[OdooConfig] Full sync error: {$e->getMessage()}");
        }

        return $this->respondSuccess([
            'config' => [
                'id' => $config->id,
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
            ],
        ]);
    }

    public function get()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondNotFound('Odoo not configured');
        }

        return $this->respondSuccess([
            'config' => [
                'id' => $config->id,
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'hasGeminiKey' => !empty($config->geminiApiKey),
            ],
        ]);
    }

    public function saveGeminiKey()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $geminiApiKey = $data['geminiApiKey'] ?? '';

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondNotFound('Odoo not configured');
        }

        $configModel->updateGeminiKey($userId, $geminiApiKey);
        return $this->respondSuccess(['ok' => true, 'hasGeminiKey' => !empty($geminiApiKey)]);
    }

    public function test()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['connected' => false, 'error' => 'Odoo not configured']);
        }

        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);
            $odoo->authenticate();
            return $this->respondSuccess(['connected' => true]);
        } catch (\Exception $e) {
            return $this->respondSuccess(['connected' => false, 'error' => $e->getMessage()]);
        }
    }
}
