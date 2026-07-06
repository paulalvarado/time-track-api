<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Models\SyncTimesheetModel;
use App\Models\SyncStateModel;
use App\Services\OdooService;

class TimesheetController extends BaseController
{
    public function listByTask($odooId, $taskId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['timesheets' => []]);
        }

        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);

            $odoo->authenticate();
            $raw = $odoo->fetchTimesheets((int) $taskId);

            $userIds = [];
            foreach ($raw as $t) {
                if (!empty($t['user_id'])) {
                    $userId2 = is_array($t['user_id']) ? $t['user_id'][0] : $t['user_id'];
                    $userIds[] = $userId2;
                }
            }
            $userNames = $odoo->fetchUserNames(array_values(array_unique($userIds)));

            $timesheets = array_map(function ($t) use ($userNames) {
                $uid = is_array($t['user_id'] ?? null) ? $t['user_id'][0] : ($t['user_id'] ?? null);
                return [
                    'id' => $t['id'],
                    'name' => $t['name'] ?? '',
                    'hours' => (float) ($t['unit_amount'] ?? 0),
                    'date' => $t['date'] ?? null,
                    'userName' => $uid ? ($userNames[$uid] ?? ('User #' . $uid)) : '',
                ];
            }, $raw);

            return $this->respondSuccess(['timesheets' => $timesheets]);
        } catch (\Exception $e) {
            return $this->respondSuccess(['timesheets' => [], 'error' => $e->getMessage()]);
        }
    }

    public function update($odooId, $taskId, $timesheetId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $name = $data['name'] ?? null;
        $hours = $data['hours'] ?? null;
        $date = $data['date'] ?? null;
        $userId2 = $data['userId'] ?? null;

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondNotFound('Odoo not configured');
        }

        $odoo = new OdooService([
            'url' => $config->url,
            'dbName' => $config->dbName,
            'username' => $config->username,
            'apiKey' => $config->apiKey,
        ]);

        $odoo->authenticate();
        $tsId = (int) $timesheetId;
        if ($tsId <= 0) {
            return $this->respondError('Invalid timesheet ID');
        }

        $values = [];
        if ($name !== null) $values['name'] = $name;
        if ($hours !== null) $values['unit_amount'] = (float) $hours;
        if ($date !== null) $values['date'] = $date;
        if ($userId2 !== null) $values['employee_id'] = (int) $userId2;

        if (empty($values)) {
            return $this->respondError('No fields to update');
        }

        try {
            $updated = $odoo->updateRecord('account.analytic.line', $tsId, $values);
        } catch (\Exception $e) {
            return $this->respondError("Odoo update failed: {$e->getMessage()}", 502);
        }

        if (!$updated) {
            return $this->respondError('Odoo returned false on write', 502);
        }

        // Update local DB
        try {
            $tsModel = new SyncTimesheetModel();
            $updateData = [];
            if ($name !== null) $updateData['name'] = $name;
            if ($hours !== null) $updateData['unitAmount'] = (float) $hours;
            if ($date !== null) $updateData['date'] = $date;
            if ($userId2 !== null) $updateData['userOdooId'] = (int) $userId2;
            $tsModel->upsert($tsId, $config->id, $updateData);
        } catch (\Exception $e) {
            log_message('error', "[Timesheet] Local update failed for {$tsId}: {$e->getMessage()}");
        }

        return $this->respondSuccess([
            'ok' => true,
            'timesheet' => [
                'id' => $tsId,
                'name' => $name ?? null,
                'hours' => $hours ?? null,
                'date' => $date ?? null,
            ],
        ]);
    }

    public function batchCreate($odooId, $taskId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $entries = $data['entries'] ?? [];

        if (empty($entries)) {
            return $this->respondError('No entries provided');
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondNotFound('Odoo not configured');
        }

        $odoo = new OdooService([
            'url' => $config->url,
            'dbName' => $config->dbName,
            'username' => $config->username,
            'apiKey' => $config->apiKey,
        ]);

        $odoo->authenticate();

        $stateModel = new SyncStateModel();
        $state = $stateModel->findByConfigId($config->id);
        $odooUid = $state->odooUid ?? null;

        $results = [];

        foreach ($entries as $i => $entry) {
            $values = [
                'task_id' => (int) $taskId,
                'name' => $entry['concept'] ?? '',
                'unit_amount' => (float) ($entry['hours'] ?? 0),
                'date' => $entry['date'] ?? date('Y-m-d'),
                'user_id' => $entry['userId'] ?? $odooUid,
            ];

            try {
                $newOdooId = $odoo->createRecord('account.analytic.line', $values);

                $tsModel = new SyncTimesheetModel();
                $tsModel->upsert($newOdooId, $config->id, [
                    'name' => $entry['concept'] ?? '',
                    'unitAmount' => (float) ($entry['hours'] ?? 0),
                    'date' => $entry['date'] ?? date('Y-m-d'),
                    'userOdooId' => $entry['userId'] ?? $odooUid,
                    'taskOdooId' => (int) $taskId,
                ]);

                $results[] = [
                    'index' => $i,
                    'concept' => $entry['concept'] ?? '',
                    'hours' => $entry['hours'] ?? 0,
                    'date' => $entry['date'] ?? date('Y-m-d'),
                    'success' => true,
                    'odooId' => $newOdooId,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $i,
                    'concept' => $entry['concept'] ?? '',
                    'hours' => $entry['hours'] ?? 0,
                    'date' => $entry['date'] ?? date('Y-m-d'),
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $succeeded = count(array_filter($results, fn($r) => $r['success']));
        $failed = count($results) - $succeeded;

        if ($failed === 0) {
            return $this->respondSuccess([
                'ok' => true,
                'message' => "All {$succeeded} entries created successfully.",
                'results' => $results,
            ]);
        } elseif ($succeeded > 0) {
            return $this->respondSuccess([
                'ok' => true,
                'partial' => true,
                'message' => "{$succeeded} entries created, {$failed} failed.",
                'results' => $results,
            ]);
        } else {
            return $this->response->setStatusCode(502)->setJSON([
                'ok' => false,
                'message' => 'All entries failed.',
                'results' => $results,
            ]);
        }
    }
}
