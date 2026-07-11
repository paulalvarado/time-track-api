<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Services\OdooService;

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
                'apiKey' => $config->apiKey ?? '',
                'hasGeminiKey' => !empty($config->geminiApiKey),
                'aiProvider' => $config->aiProvider ?? null,
                'aiApiKey' => $config->aiApiKey ?? null,
                'aiBaseUrl' => $config->aiBaseUrl ?? null,
                'aiModel' => $config->aiModel ?? null,
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

    public function saveAiConfig()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $aiProvider = $data['aiProvider'] ?? 'gemini';
        $aiApiKey = $data['aiApiKey'] ?? '';
        $aiBaseUrl = $data['aiBaseUrl'] ?? '';
        $aiModel = $data['aiModel'] ?? '';

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondNotFound('Odoo not configured');
        }

        $configModel->update($config->id, [
            'aiProvider' => $aiProvider,
            'aiApiKey' => $aiApiKey,
            'aiBaseUrl' => $aiBaseUrl,
            'aiModel' => $aiModel,
        ]);

        return $this->respondSuccess(['ok' => true, 'hasAiConfig' => !empty($aiApiKey)]);
    }

    public function testAiConfig()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $provider = $data['aiProvider'] ?? 'gemini';
        $apiKey = $data['aiApiKey'] ?? '';
        $baseUrl = $data['aiBaseUrl'] ?? '';
        $model = $data['aiModel'] ?? '';

        if (empty($apiKey)) {
            return $this->respondError('API key is required');
        }

        // Try a simple validation request based on provider
        try {
            $ch = curl_init();

            switch ($provider) {
                case 'openai':
                    curl_setopt_array($ch, [
                        CURLOPT_URL => rtrim($baseUrl, '/') . '/models',
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $apiKey,
                            'Content-Type: application/json',
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    break;

                case 'anthropic':
                    curl_setopt_array($ch, [
                        CURLOPT_URL => rtrim($baseUrl, '/') . '/messages',
                        CURLOPT_HTTPHEADER => [
                            'x-api-key: ' . $apiKey,
                            'anthropic-version: 2023-06-01',
                            'Content-Type: application/json',
                        ],
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode([
                            'model' => $model,
                            'max_tokens' => 1,
                            'messages' => [['role' => 'user', 'content' => 'Hi']],
                        ]),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    break;

                case 'gemini':
                default:
                    curl_setopt_array($ch, [
                        CURLOPT_URL => rtrim($baseUrl, '/') . '/models?key=' . urlencode($apiKey),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    break;
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return $this->respondError('Connection error: ' . $curlError);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return $this->respondSuccess(['ok' => true, 'message' => 'Conexión exitosa con ' . $provider]);
            }

            // Try to extract error message from response
            $decoded = json_decode($response, true);
            $errorMsg = $decoded['error']['message'] ?? ($decoded['error'] ?? 'HTTP ' . $httpCode);
            return $this->respondError('Error: ' . $errorMsg);

        } catch (\Exception $e) {
            return $this->respondError('Error: ' . $e->getMessage());
        }
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

    public function listEmployees()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondError('Odoo not configured');
        }

        // Leer empleados desde el catálogo local (sincronizado por sync:catalogs)
        $catalogModel = new \App\Models\CatalogModel();
        $catalog = $catalogModel->findByName('employees', $config->id);

        if (!$catalog) {
            return $this->respondSuccess(['employees' => []]);
        }

        $itemModel = new \App\Models\CatalogItemModel();
        $items = $itemModel->findByCatalogId($catalog->id);

        $employees = array_map(function ($item) {
            $extra = $item->extra ? json_decode($item->extra, true) : null;
            return [
                'id'     => (int) $item->key,
                'name'   => $item->value,
                'userId' => $extra['userId'] ?? null,
            ];
        }, $items);

        return $this->respondSuccess(['employees' => $employees]);
    }

    public function saveEmployeePreference()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $employeeId = $data['employeeId'] ?? null;
        $odooUserId = $data['odooUserId'] ?? null;

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondError('Odoo not configured');
        }

        $configModel->update($config->id, [
            'selectedEmployeeId' => $employeeId ? (int) $employeeId : null,
            'selectedOdooUserId' => $odooUserId ? (int) $odooUserId : null,
        ]);

        return $this->respondSuccess(['ok' => true]);
    }

    public function getEmployeePreference()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess([
                'selectedEmployeeId' => null,
                'selectedOdooUserId' => null,
            ]);
        }

        return $this->respondSuccess([
            'selectedEmployeeId' => $config->selectedEmployeeId ?? null,
            'selectedOdooUserId' => $config->selectedOdooUserId ?? null,
        ]);
    }

    public function timesheetsAll()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondError('Odoo not configured');
        }

        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);
            $raw = $odoo->fetchAllTimesheetsRaw();

            // Collect project IDs to resolve names
            $projectIds = [];
            foreach ($raw as $t) {
                if (!empty($t['project_id'])) {
                    $pid = is_array($t['project_id']) ? $t['project_id'][0] : $t['project_id'];
                    $projectIds[] = (int) $pid;
                }
            }
            $projectNames = $odoo->fetchProjectNames(array_values(array_unique($projectIds)));

            $timesheets = array_map(function ($t) use ($projectNames) {
                $pid = is_array($t['project_id'] ?? null) ? $t['project_id'][0] : ($t['project_id'] ?? null);
                $tid = is_array($t['task_id'] ?? null) ? $t['task_id'][0] : ($t['task_id'] ?? null);
                $tuid = is_array($t['user_id'] ?? null) ? $t['user_id'][0] : ($t['user_id'] ?? null);
                $teid = is_array($t['employee_id'] ?? null) ? $t['employee_id'][0] : ($t['employee_id'] ?? null);
                return [
                    'id' => $t['id'],
                    'name' => $t['name'] ?? '',
                    'hours' => (float) ($t['unit_amount'] ?? 0),
                    'date' => $t['date'] ?? null,
                    'taskId' => $tid ? (int) $tid : null,
                    'projectId' => $pid ? (int) $pid : null,
                    'projectName' => $pid ? ($projectNames[(int) $pid] ?? 'Project #' . $pid) : null,
                    'userId' => $tuid ? (int) $tuid : null,
                    'employeeId' => $teid ? (int) $teid : null,
                ];
            }, $raw);

            return $this->respondSuccess(['timesheets' => $timesheets]);
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage());
        }
    }
}
