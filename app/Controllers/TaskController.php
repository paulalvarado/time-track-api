<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Services\OdooService;

class TaskController extends BaseController
{
    public function listByProject($odooId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['tasks' => [], 'stages' => []]);
        }

        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);

            $odooUid = $odoo->authenticate();

            // Fetch stages
            $rawStages = $odoo->fetchStageNames((int) $odooId);
            usort($rawStages, function ($a, $b) {
                return ($a['sequence'] - $b['sequence']) ?: ($a['id'] - $b['id']);
            });
            $stages = array_map(function ($s) {
                return ['id' => $s['id'], 'name' => $s['name'], 'sequence' => $s['sequence']];
            }, $rawStages);
            $stageMap = [];
            foreach ($stages as $s) {
                $stageMap[$s['id']] = $s['name'];
            }

            // Fetch tasks
            $rawTasks = $odoo->fetchTasks((int) $odooId);

            // Collect user IDs
            $userIds = [];
            foreach ($rawTasks as $t) {
                if (!empty($t['user_ids'])) {
                    foreach ($t['user_ids'] as $id) {
                        if (is_int($id)) {
                            $userIds[] = $id;
                        }
                    }
                }
            }
            $userNames = $odoo->fetchUserNames(array_values(array_unique($userIds)));

            $tasks = array_map(function ($t) use ($stageMap, $userNames, $odooUid) {
                $rawAssignees = $t['user_ids'] ?? [];
                $assignees = [];
                foreach ($rawAssignees as $a) {
                    if (is_array($a) && count($a) >= 2) {
                        $assignees[] = [$a[0], $a[1]];
                    } elseif (is_int($a)) {
                        $assignees[] = [$a, $userNames[$a] ?? ('User #' . $a)];
                    } elseif (is_string($a)) {
                        $assignees[] = [(int) $a, $userNames[(int) $a] ?? ('User #' . $a)];
                    }
                }
                return [
                    'id' => $t['id'],
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'stageId' => (!empty($t['stage_id']) && is_array($t['stage_id'])) ? $t['stage_id'][0] : null,
                    'stageName' => isset($t['stage_id']) ? ($stageMap[$t['stage_id'][0]] ?? $t['stage_id'][1]) : 'Uncategorized',
                    'assignees' => $assignees,
                    'priority' => $t['priority'] ?? '0',
                    'deadline' => $t['date_deadline'] ?? null,
                    'color' => $t['color'] ?? null,
                    'isMyTask' => in_array($odooUid, array_column($assignees, 0)),
                ];
            }, $rawTasks);

            return $this->respondSuccess(['tasks' => $tasks, 'stages' => $stages]);
        } catch (\Exception $e) {
            return $this->respondSuccess(['tasks' => [], 'stages' => [], 'error' => $e->getMessage()]);
        }
    }
}
