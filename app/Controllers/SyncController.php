<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Models\SyncProjectModel;
use App\Models\SyncStateModel;
use App\Models\SyncTaskModel;
use App\Models\SyncStageModel;
use App\Models\SyncProjectStageModel;
use App\Models\SyncTimesheetModel;
use App\Models\CatalogModel;
use App\Models\CatalogItemModel;
use App\Services\OdooService;
use App\Services\SyncService;

class SyncController extends BaseController
{
    public function listProjects()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $since = $this->request->getGet('since');

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['projects' => [], 'odooConnected' => false]);
        }

        $stateModel = new SyncStateModel();
        $state = $stateModel->findByConfigId($config->id);
        $u = $state->odooUid ?? null;
        $odooUid = ($u !== null) ? (int) $u : null;

        $projectModel = new SyncProjectModel();

        // If ?since=, return only changed records
        if ($since) {
            $sinceDate = date('Y-m-d H:i:s', strtotime($since));
            if ($sinceDate) {
                $db = \Config\Database::connect();
                $builder = $db->table('public.syncproject');
                $builder->select('"odooId", name, color, "odooUserId", "updatedAt"');
                $builder->where('"odooConfigId"', $config->id);
                $builder->where('"updatedAt" >', $sinceDate);
                $changed = $builder->get()->getResult();

                $changed = array_map(function ($c) use ($odooUid) {
                    $c->isMine = ($odooUid !== null && $c->odooUserId === $odooUid);
                    return $c;
                }, $changed);

                return $this->respondSuccess([
                    'changed' => $changed,
                    'syncing' => self::castBool($state->syncing ?? false),
                    'lastSyncAt' => $state->lastSyncAt ? date('c', strtotime($state->lastSyncAt)) : null,
                ]);
            }
        }

        // Full response
        $projects = $projectModel->findByConfig($config->id);
        $projects = array_map(function ($p) use ($odooUid) {
            $pUserOdooId = $p->odooUserId !== null ? (int) $p->odooUserId : null;
            $p->isMine = ($odooUid !== null && $pUserOdooId === $odooUid);
            return $p;
        }, $projects);

        usort($projects, function ($a, $b) {
            if ($a->isMine && !$b->isMine) return -1;
            if (!$a->isMine && $b->isMine) return 1;
            return strcasecmp($a->name, $b->name);
        });

        return $this->respondSuccess([
            'projects' => $projects,
            'syncing' => self::castBool($state->syncing ?? false),
            'lastSyncAt' => $state->lastSyncAt ? date('c', strtotime($state->lastSyncAt)) : null,
        ]);
    }

    public function listStages($projectId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['stages' => []]);
        }

        $taskModel = new SyncTaskModel();
        $stageIds = $taskModel->select('"stageOdooId"')
            ->distinct()
            ->where('"odooConfigId"', $config->id)
            ->where('"projectOdooId"', (int) $projectId)
            ->findAll();

        $stageIds = array_filter(array_map(function ($s) {
            return $s->stageOdooId;
        }, $stageIds));

        if (empty($stageIds)) {
            return $this->respondSuccess(['stages' => []]);
        }

        $stageModel = new SyncStageModel();
        $stages = $stageModel->whereIn('"odooId"', $stageIds)
            ->where('"odooConfigId"', $config->id)
            ->orderBy('"sequence"', 'ASC')
            ->orderBy('"odooId"', 'ASC')
            ->findAll();

        return $this->respondSuccess(['stages' => $stages]);
    }

    public function listTasks($projectId)
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

        $stateModel = new SyncStateModel();
        $state = $stateModel->findByConfigId($config->id);

        $projectModel = new SyncProjectModel();
        $syncProject = $projectModel->findByOdooId((int) $projectId, $config->id);

        $taskModel = new SyncTaskModel();
        $rawTasks = $taskModel->findByProject((int) $projectId, $config->id);

        // Get used stage IDs from tasks
        $usedStageIds = array_filter(array_unique(array_map(function ($t) {
            return $t->stageOdooId;
        }, $rawTasks)));

        // Also get explicit stages from SyncProjectStage
        $psModel = new SyncProjectStageModel();
        $explicitStages = $psModel->findByProject((int) $projectId, $config->id);
        foreach ($explicitStages as $es) {
            if (!in_array($es->stageOdooId, $usedStageIds)) {
                $usedStageIds[] = $es->stageOdooId;
            }
        }

        $stageModel = new SyncStageModel();
        $stages = [];
        if (!empty($usedStageIds)) {
            $stages = $stageModel->whereIn('"odooId"', $usedStageIds)
                ->where('"odooConfigId"', $config->id)
                ->orderBy('"sequence"', 'ASC')
                ->orderBy('"odooId"', 'ASC')
                ->findAll();
        }
        $stageMap = [];
        foreach ($stages as $s) {
            $stageMap[$s->odooId] = $s->name;
        }

        $tasks = array_map(function ($t) use ($stageMap) {
            return [
                'id' => $t->odooId,
                'name' => $t->name,
                'description' => $t->description ?? '',
                'stageId' => $t->stageOdooId,
                'stageName' => $t->stageOdooId ? ($stageMap[$t->stageOdooId] ?? 'Uncategorized') : 'Uncategorized',
                'assignees' => json_decode($t->assigneeIds ?? '[]', true) ?: [],
                'priority' => $t->priority ?? '0',
                'deadline' => $t->deadline ?? null,
                'color' => $t->color ?? null,
            ];
        }, $rawTasks);

        return $this->respondSuccess([
            'tasks' => $tasks,
            'projectName' => $syncProject->name ?? null,
            'stages' => array_map(function ($s) {
                return ['id' => $s->odooId, 'name' => $s->name];
            }, $stages),
            'syncing' => self::castBool($state->syncing ?? false),
            'lastSyncAt' => $state->lastSyncAt ? date('c', strtotime($state->lastSyncAt)) : null,
        ]);
    }

    public function getTask($projectId, $taskId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['task' => null, 'projectName' => null]);
        }

        $taskModel = new SyncTaskModel();
        $task = $taskModel->findByOdooId((int) $taskId, $config->id);

        $projectModel = new SyncProjectModel();
        $syncProject = $projectModel->findByOdooId((int) $projectId, $config->id);

        if (!$task) {
            return $this->respondSuccess(['task' => null, 'projectName' => $syncProject->name ?? null]);
        }

        $stageName = 'Uncategorized';
        if ($task->stageOdooId) {
            $stageModel = new SyncStageModel();
            $stage = $stageModel->findByOdooId($task->stageOdooId, $config->id);
            $stageName = $stage->name ?? 'Uncategorized';
        }

        return $this->respondSuccess([
            'task' => [
                'id' => $task->odooId,
                'name' => $task->name,
                'description' => $task->description ?? '',
                'stageId' => $task->stageOdooId,
                'stageName' => $stageName,
                'assignees' => json_decode($task->assigneeIds ?? '[]', true) ?: [],
                'priority' => $task->priority ?? '0',
                'deadline' => $task->deadline ?? null,
                'color' => $task->color ?? null,
            ],
            'projectName' => $syncProject->name ?? null,
        ]);
    }

    public function listTimesheets($projectId, $taskId)
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

        $tsModel = new SyncTimesheetModel();
        $raw = $tsModel->findByTask((int) $taskId, $config->id);

        // Build user name cache
        $userCache = [];
        $userIds = array_values(array_filter(array_unique(array_map(function ($t) {
            return $t->userOdooId;
        }, $raw))));

        // Look up from task assignees
        $taskModel = new SyncTaskModel();
        $tasks = $taskModel->where('"odooConfigId"', $config->id)->findAll();
        foreach ($tasks as $task) {
            $assignees = json_decode($task->assigneeIds ?? '[]', true) ?: [];
            foreach ($assignees as $a) {
                if (count($a) >= 2) {
                    $userCache[$a[0]] = $a[1];
                }
            }
        }

        // Try to fetch missing names from Odoo
        $missingIds = array_values(array_filter($userIds, function ($id) use ($userCache) {
            return !isset($userCache[$id]);
        }));
        if (!empty($missingIds)) {
            try {
                $odoo = new OdooService([
                    'url' => $config->url, 'dbName' => $config->dbName,
                    'username' => $config->username, 'apiKey' => $config->apiKey,
                ]);
                $odoo->authenticate();
                $userNames = $odoo->fetchUserNames($missingIds);
                foreach ($userNames as $id => $name) {
                    if (!isset($userCache[$id])) {
                        $userCache[$id] = $name;
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Get employee catalog
        $employeeById = [];
        $catalogModel = new CatalogModel();
        $empCatalog = $catalogModel->findByName('employees', $config->id);
        if ($empCatalog) {
            $itemModel = new CatalogItemModel();
            $items = $itemModel->findByCatalogId($empCatalog->id);
            foreach ($items as $item) {
                $employeeById[(int) $item->key] = $item->value;
            }
        }

        $timesheets = array_map(function ($t) use ($employeeById, $userCache) {
            $userName = '';
            if ($t->userOdooId) {
                $userName = $employeeById[$t->userOdooId] ?? $userCache[$t->userOdooId] ?? ('User #' . $t->userOdooId);
            }
            return [
                'id' => $t->odooId,
                'name' => $t->name ?? '',
                'hours' => (float) $t->unitAmount,
                'date' => $t->date ?? null,
                'userName' => $userName,
                'userId' => $t->userOdooId ?? null,
            ];
        }, $raw);

        return $this->respondSuccess(['timesheets' => $timesheets]);
    }

    public function status()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['configured' => false]);
        }

        $stateModel = new SyncStateModel();
        $state = $stateModel->findByConfigId($config->id);

        return $this->respondSuccess([
            'configured' => true,
            'syncing' => self::castBool($state->syncing ?? false),
            'lastSyncAt' => $state->lastSyncAt ? date('c', strtotime($state->lastSyncAt)) : null,
            'error' => $state->error ?? null,
            'odooUid' => self::castInt($state->odooUid ?? null),
        ]);
    }

    public function totalHours()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['totalHours' => 0]);
        }

        $stateModel = new SyncStateModel();
        $state = $stateModel->findByConfigId($config->id);
        $u = $state->odooUid ?? null;
        $odooUid = ($u !== null) ? (int) $u : null;

        if ($odooUid === null) {
            return $this->respondSuccess(['totalHours' => 0]);
        }

        $tsModel = new SyncTimesheetModel();
        $totalHours = $tsModel->sumByUser($odooUid, $config->id);

        return $this->respondSuccess(['totalHours' => $totalHours]);
    }

    private static function castBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_string($value)) return ($value === 't' || $value === 'true' || $value === '1');
        return (bool) $value;
    }

    private static function castInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        return (int) $value;
    }
}
