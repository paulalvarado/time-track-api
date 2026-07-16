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

        // Filter by odooUserId (admin feature)
        $filterUserId = $this->request->getGet('odooUserId');
        if ($filterUserId !== null && ($this->request->isAdmin ?? false)) {
            $filterUserId = (int) $filterUserId;
            $projects = array_filter($projects, function ($p) use ($filterUserId) {
                return $p->odooUserId !== null && (int) $p->odooUserId === $filterUserId;
            });
        }

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

    public function listProjectUsers()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['users' => []]);
        }

        // Get distinct odooUserId from syncproject
        $db = \Config\Database::connect();
        $builder = $db->table('public.syncproject');
        $builder->distinct()->select('"odooUserId"');
        $builder->where('"odooConfigId"', $config->id);
        $builder->where('"odooUserId" IS NOT NULL');
        $rows = $builder->get()->getResult();

        $userIds = array_map(function ($r) {
            return (int) $r->odooUserId;
        }, $rows);

        if (empty($userIds)) {
            return $this->respondSuccess(['users' => []]);
        }

        // Get user names from Odoo
        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);
            $odoo->authenticate();
            $userNames = $odoo->fetchUserNames($userIds);
        } catch (\Throwable $e) {
            $userNames = [];
        }

        $users = [];
        foreach ($userIds as $id) {
            $users[] = [
                'odooUserId' => $id,
                'name' => $userNames[$id] ?? ('User #' . $id),
            ];
        }

        // Sort by name
        usort($users, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $this->respondSuccess(['users' => $users]);
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
        $userToEmployeeId = [];
        $catalogModel = new CatalogModel();
        $empCatalog = $catalogModel->findByName('employees', $config->id);
        if ($empCatalog) {
            $itemModel = new CatalogItemModel();
            $items = $itemModel->findByCatalogId($empCatalog->id);
            foreach ($items as $item) {
                $employeeById[(int) $item->key] = $item->value;
                if ($item->extra) {
                    $extra = json_decode($item->extra, true);
                    if (!empty($extra['userId'])) {
                        $userToEmployeeId[(int) $extra['userId']] = (int) $item->key;
                    }
                }
            }
        }

        $timesheets = array_map(function ($t) use ($employeeById, $userToEmployeeId, $userCache) {
            $userName = '';
            if ($t->userOdooId) {
                $userName = $employeeById[$t->userOdooId] ?? $userCache[$t->userOdooId] ?? ('User #' . $t->userOdooId);
            }
            // Prefer employeeOdooId if available, otherwise map via userToEmployeeId
            $employeeId = $t->employeeOdooId ?? null;
            if (!$employeeId && $t->userOdooId) {
                $employeeId = $userToEmployeeId[$t->userOdooId] ?? null;
            }
            return [
                'id' => $t->odooId,
                'name' => $t->name ?? '',
                'hours' => (float) $t->unitAmount,
                'date' => $t->date ?? null,
                'userName' => $userName,
                'userId' => $employeeId ?? $t->userOdooId ?? null,
            ];
        }, $raw);

        return $this->respondSuccess(['timesheets' => $timesheets]);
    }

    /**
     * Marcar un proyecto como "trackeado" (visitado) y, si es la primera vez,
     * obtener sus datos desde Odoo directamente (tareas, etapas, partes de hora).
     *
     * POST /api/sync/projects/{projectId}/track
     */
    public function trackProject($projectId)
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

        $projectModel = new SyncProjectModel();
        $syncProject = $projectModel->findByOdooId((int) $projectId, $config->id);

        if (!$syncProject) {
            return $this->respondNotFound('Project not found in local DB');
        }

        // Si el proyecto ya es del usuario, no necesita trackeo
        $stateModel = new SyncStateModel();
        $state = $stateModel->findByConfigId($config->id);
        $u = $state->odooUid ?? null;
        $odooUid = ($u !== null) ? (int) $u : null;

        $projectUserId = $syncProject->odooUserId !== null ? (int) $syncProject->odooUserId : null;
        if ($odooUid !== null && $projectUserId === $odooUid) {
            return $this->respondSuccess(['tracked' => true, 'message' => 'Project is owned by you, no tracking needed']);
        }

        // Marcar como trackeado
        $projectModel->markTracked((int) $projectId, $config->id);

        // Verificar si ya tiene tareas en DB local
        $taskModel = new SyncTaskModel();
        $existingTasks = $taskModel->findByProject((int) $projectId, $config->id);

        if (!empty($existingTasks)) {
            return $this->respondSuccess(['tracked' => true, 'message' => 'Project already synced']);
        }

        // Primera vez: obtener datos desde Odoo directamente
        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);
            $odoo->authenticate();

            // 1. Obtener etapas del proyecto
            $rawStages = $odoo->fetchStageNames((int) $projectId);

            // Guardar etapas en syncstage
            $stageModel = new SyncStageModel();
            foreach ($rawStages as $stage) {
                $stageModel->upsert($stage['id'], $config->id, [
                    'name' => $stage['name'],
                    'sequence' => $stage['sequence'],
                ]);
            }

            // 2. Obtener tareas del proyecto
            $rawTasks = $odoo->fetchTasks((int) $projectId);

            // Obtener nombres de usuarios asignados
            $allUserIds = [];
            foreach ($rawTasks as $task) {
                if (!empty($task['user_ids'])) {
                    foreach ($task['user_ids'] as $id) {
                        if (is_int($id)) {
                            $allUserIds[] = $id;
                        }
                    }
                }
            }
            $userNames = $odoo->fetchUserNames(array_values(array_unique($allUserIds)));

            // Guardar tareas
            $taskIds = [];
            foreach ($rawTasks as $task) {
                $rawAssignees = $task['user_ids'] ?? [];
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

                $stageId = null;
                if (!empty($task['stage_id']) && is_array($task['stage_id'])) {
                    $stageId = (int) $task['stage_id'][0];
                }

                $deadline = $task['date_deadline'] ?? null;
                if ($deadline === false || $deadline === 'false') $deadline = null;
                $color = $task['color'] ?? null;
                if ($color === false || $color === 'false') $color = null;

                $taskModel->upsert($task['id'], $config->id, [
                    'name' => $task['name'],
                    'description' => $task['description'] ?? '',
                    'stageOdooId' => $stageId,
                    'assigneeIds' => json_encode($assignees),
                    'priority' => $task['priority'] ?? '0',
                    'deadline' => $deadline,
                    'color' => $color,
                    'projectOdooId' => (int) $projectId,
                ]);

                $taskIds[] = (int) $task['id'];
            }

            // 3. Obtener partes de hora de esas tareas
            if (!empty($taskIds)) {
                $allTimesheets = $odoo->fetchAllTimesheets($taskIds);

                // Obtener empleados para mapeo employee→user
                $employeeUserMap = [];
                try {
                    $employees = $odoo->fetchEmployees();
                    foreach ($employees as $emp) {
                        if ($emp['userId'] !== null) {
                            $employeeUserMap[$emp['id']] = $emp['userId'];
                        }
                    }
                } catch (\Throwable $e) {
                    log_message('error', "[TrackProject] Could not fetch employees: {$e->getMessage()}");
                }

                // Guardar partes de hora
                $tsModel = new SyncTimesheetModel();
                foreach ($allTimesheets as $ts) {
                    $tsTaskOdooId = is_array($ts['task_id'] ?? null) ? (int) $ts['task_id'][0] : (int) ($ts['task_id'] ?? 0);

                    $userId2 = null;
                    if (!empty($ts['employee_id']) && $ts['employee_id'] !== false) {
                        $empId = is_array($ts['employee_id']) ? (int) $ts['employee_id'][0] : (int) $ts['employee_id'];
                        $userId2 = $employeeUserMap[$empId] ?? $empId;
                    }
                    if ($userId2 === null && !empty($ts['user_id']) && $ts['user_id'] !== false) {
                        $userId2 = is_array($ts['user_id']) ? (int) $ts['user_id'][0] : (int) $ts['user_id'];
                    }

                    $tsDate = $ts['date'] ?? null;
                    if ($tsDate === false || $tsDate === 'false') $tsDate = null;
                    $tsName = $ts['name'] ?? '';
                    if ($tsName === false) $tsName = '';
                    if (mb_strlen($tsName) > 255) $tsName = mb_substr($tsName, 0, 255);

                    $tsUpsertData = [
                        'name' => $tsName,
                        'unitAmount' => (float) ($ts['unit_amount'] ?? 0),
                        'date' => $tsDate,
                        'userOdooId' => $userId2,
                        'taskOdooId' => $tsTaskOdooId,
                    ];
                    if (!empty($ts['employee_id']) && $ts['employee_id'] !== false) {
                        $empId = is_array($ts['employee_id']) ? (int) $ts['employee_id'][0] : (int) $ts['employee_id'];
                        $tsUpsertData['employeeOdooId'] = $empId;
                    }
                    $tsModel->upsert((int) $ts['id'], $config->id, $tsUpsertData);
                }
            }

            return $this->respondSuccess(['tracked' => true, 'message' => 'Project data fetched from Odoo and saved locally']);
        } catch (\Exception $e) {
            return $this->respondError("Failed to fetch project data from Odoo: {$e->getMessage()}", 502);
        }
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

    /**
     * GET /api/sync/hours-by-employee/(:num)
     * Retorna el total de horas desde la BD local filtrado por employeeOdooId.
     * Query params: ?period=day|week|month|year
     */
    public function hoursByEmployee(int $employeeOdooId)
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

        $period = $this->request->getGet('period') ?? 'week';

        $tsModel = new SyncTimesheetModel();

        if (in_array($period, ['day', 'week', 'month', 'year'], true)) {
            $totalHours = $tsModel->sumByEmployeePeriod($employeeOdooId, $config->id, $period);
        } else {
            $totalHours = $tsModel->sumByEmployee($employeeOdooId, $config->id);
        }

        return $this->respondSuccess(['totalHours' => $totalHours]);
    }

    public function createTask($projectId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $name = $data['name'] ?? '';
        $stageId = $data['stageId'] ?? null;
        $ownerId = $data['ownerId'] ?? null;
        $color = $data['color'] ?? null;
        $description = $data['description'] ?? '';

        if (empty($name)) {
            return $this->respondError('Task name is required');
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

        $values = [
            'name' => $name,
            'project_id' => (int) $projectId,
        ];
        if ($stageId !== null) $values['stage_id'] = (int) $stageId;
        if ($ownerId !== null) $values['user_ids'] = [(int) $ownerId];
        if ($color !== null) $values['color'] = (int) $color;
        if (!empty($description)) $values['description'] = $description;

        try {
            $newOdooId = $odoo->createRecord('project.task', $values);
        } catch (\Exception $e) {
            return $this->respondError("Odoo create error: {$e->getMessage()}", 502);
        }

        // Save to local DB
        try {
            $taskModel = new SyncTaskModel();
            $taskModel->upsert($newOdooId, $config->id, [
                'name' => $name,
                'description' => $description,
                'stageOdooId' => $stageId ? (int) $stageId : null,
                'assigneeIds' => $ownerId ? json_encode([[$ownerId, '']]) : '[]',
                'priority' => '0',
                'color' => $color !== null ? (int) $color : null,
                'projectOdooId' => (int) $projectId,
            ]);
        } catch (\Exception $e) {
            log_message('error', "[Task] Local save failed for {$newOdooId}: {$e->getMessage()}");
        }

        return $this->respondSuccess([
            'ok' => true,
            'task' => [
                'id' => $newOdooId,
                'name' => $name,
                'stageId' => $stageId,
                'ownerId' => $ownerId,
                'color' => $color,
            ],
        ]);
    }

    public function updateTask($projectId, $taskId)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $name = $data['name'] ?? null;
        $stageId = $data['stageId'] ?? null;
        $ownerId = $data['ownerId'] ?? null;
        $color = $data['color'] ?? null;
        $description = $data['description'] ?? null;

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

        $values = [];
        if ($name !== null) $values['name'] = $name;
        if ($stageId !== null) $values['stage_id'] = (int) $stageId;
        if ($ownerId !== null) $values['user_ids'] = [(int) $ownerId];
        if ($color !== null) $values['color'] = (int) $color;
        if ($description !== null) $values['description'] = $description;

        if (empty($values)) {
            return $this->respondError('No fields to update');
        }

        try {
            $updated = $odoo->updateRecord('project.task', (int) $taskId, $values);
        } catch (\Exception $e) {
            return $this->respondError("Odoo update error: {$e->getMessage()}", 502);
        }

        if (!$updated) {
            return $this->respondError('Odoo returned false on update', 502);
        }

        // Update local DB
        try {
            $taskModel = new SyncTaskModel();
            $updateData = [];
            if ($name !== null) $updateData['name'] = $name;
            if ($description !== null) $updateData['description'] = $description;
            if ($stageId !== null) $updateData['stageOdooId'] = (int) $stageId;
            if ($ownerId !== null) $updateData['assigneeIds'] = json_encode([[(int) $ownerId, '']]);
            if ($color !== null) $updateData['color'] = (int) $color;
            $taskModel->upsert((int) $taskId, $config->id, $updateData);
        } catch (\Exception $e) {
            log_message('error', "[Task] Local update failed for {$taskId}: {$e->getMessage()}");
        }

        return $this->respondSuccess([
            'ok' => true,
            'task' => [
                'id' => (int) $taskId,
                'name' => $name,
                'stageId' => $stageId,
                'ownerId' => $ownerId,
                'color' => $color,
            ],
        ]);
    }

    public function deleteTask($projectId, $taskId)
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

        $odoo = new OdooService([
            'url' => $config->url,
            'dbName' => $config->dbName,
            'username' => $config->username,
            'apiKey' => $config->apiKey,
        ]);

        $odoo->authenticate();

        try {
            $deleted = $odoo->deleteRecord('project.task', (int) $taskId);
        } catch (\Exception $e) {
            return $this->respondError("Odoo delete error: {$e->getMessage()}", 502);
        }

        if (!$deleted) {
            return $this->respondError('Odoo returned false on delete', 502);
        }

        // Delete from local DB
        try {
            $taskModel = new SyncTaskModel();
            $existing = $taskModel->findByOdooId((int) $taskId, $config->id);
            if ($existing) {
                $taskModel->delete($existing->id);
            }
        } catch (\Exception $e) {
            log_message('error', "[Task] Local delete failed for {$taskId}: {$e->getMessage()}");
        }

        return $this->respondSuccess(['ok' => true, 'message' => 'Task deleted']);
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
