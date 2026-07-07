<?php

namespace App\Services;

use App\Models\OdooConfigModel;
use App\Models\SyncProjectModel;
use App\Models\SyncStateModel;
use App\Models\SyncStageModel;
use App\Models\SyncTaskModel;
use App\Models\SyncTimesheetModel;
use App\Models\SyncProjectStageModel;

class SyncService
{
    public static function syncAll(string $odooConfigId): void
    {
        $configModel = new OdooConfigModel();
        $config = $configModel->find($odooConfigId);
        if (!$config) {
            throw new \RuntimeException('OdooConfig not found');
        }

        $syncStateModel = new SyncStateModel();
        $syncStateModel->upsert($odooConfigId, ['syncing' => true, 'error' => null]);

        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);

            $odoo->authenticate();
            $odooUid = $odoo->getOdooUid();

            // ========================================================================
            // FASE 1: Proyectos (1 llamada)
            // ========================================================================
            echo "  [1/5] Fetching projects...\n";
            $odooProjects = $odoo->fetchProjects();
            echo "  [1/5] " . count($odooProjects) . " projects fetched\n";

            $syncProjectModel = new SyncProjectModel();
            foreach ($odooProjects as $project) {
                $color = $project['color'] ?? null;
                if ($color === false || $color === 'false') $color = null;
                $syncProjectModel->upsert($project['id'], $odooConfigId, [
                    'name' => $project['name'],
                    'color' => $color,
                    'odooUserId' => (!empty($project['user_id']) && is_array($project['user_id'])) ? (int) $project['user_id'][0] : null,
                ]);
            }
            echo "  [1/5] Projects saved to local DB\n";

            $projectIds = array_column($odooProjects, 'id');

            // Identify "my" projects (assigned to the authenticated Odoo user)
            $myProjectIds = [];
            foreach ($odooProjects as $project) {
                $projectUserId = (!empty($project['user_id']) && is_array($project['user_id'])) ? (int) $project['user_id'][0] : null;
                if ($odooUid !== null && $projectUserId === $odooUid) {
                    $myProjectIds[] = (int) $project['id'];
                }
            }

            if (empty($myProjectIds)) {
                echo "  ⚠️ No projects assigned to you. Sync complete (only projects saved).\n";
                $syncStateModel->upsert($odooConfigId, [
                    'syncing' => false,
                    'lastSyncAt' => date('Y-m-d H:i:s'),
                    'error' => null,
                    'odooUid' => $odooUid,
                ]);
                return;
            }

            echo "  [1/5] " . count($myProjectIds) . " projects assigned to you\n";

            // ========================================================================
            // FASE 2: Stages + relaciones proyecto-etapa (1 llamada)
            // Solo para los proyectos del usuario autenticado
            // ========================================================================
            echo "  [2/5] Fetching stages for your projects...\n";
            $allStages = [];
            $projectStagePairs = [];

            $odooStages = $odoo->fetchAllStages();
            foreach ($odooStages as $s) {
                $allStages[$s['id']] = $s;
                // Build project-stage pairs: only link stages to my projects
                if (!empty($s['project_ids']) && is_array($s['project_ids'])) {
                    foreach ($s['project_ids'] as $pid) {
                        if (in_array((int) $pid, $myProjectIds, true)) {
                            $projectStagePairs[] = (int) $s['id'] . ':' . (int) $pid;
                        }
                    }
                }
            }
            echo "  [2/5] " . count($allStages) . " stages fetched\n";

            // ========================================================================
            // FASE 3: Tareas (1 llamada masiva, filtrar solo mis proyectos)
            // ========================================================================
            echo "  [3/5] Fetching all tasks...\n";
            $odooTasks = $odoo->fetchAllTasks($projectIds);
            echo "  [3/5] " . count($odooTasks) . " tasks fetched\n";

            // Attach projectOdooId and filter only tasks from my projects
            $allTasks = [];
            foreach ($odooTasks as $t) {
                $t['projectOdooId'] = is_array($t['project_id'] ?? null) ? (int) $t['project_id'][0] : (int) ($t['project_id'] ?? 0);
                // Only keep tasks from projects assigned to this user
                if (in_array($t['projectOdooId'], $myProjectIds, true)) {
                    $allTasks[] = $t;
                }
            }
            echo "  [3/5] " . count($allTasks) . " tasks are in your projects\n";

            // ========================================================================
            // FASE 4: Timesheets (1 llamada masiva en batches de 100)
            // ========================================================================
            $taskIds = array_column($allTasks, 'id');
            echo "  [4/5] Fetching all timesheets (" . count($taskIds) . " tasks in your projects)...\n";
            $allTimesheets = $odoo->fetchAllTimesheets($taskIds);
            echo "  [4/5] " . count($allTimesheets) . " timesheet entries fetched\n";

            // ========================================================================
            // FASE 5: Procesar todo en memoria y persistir
            // ========================================================================
            echo "  [5/5] Processing and saving data...\n";

            // 5a. Fetch user names (1 llamada)
            $allUserIds = [];
            foreach ($allTasks as $task) {
                if (!empty($task['user_ids'])) {
                    foreach ($task['user_ids'] as $id) {
                        if (is_int($id)) {
                            $allUserIds[] = $id;
                        }
                    }
                }
            }
            $userNames = $odoo->fetchUserNames(array_values(array_unique($allUserIds)));

            // 5b. Fetch all employees once and build employee→userId cache (1 llamada)
            echo "  [5/5] Caching employee→user mapping...\n";
            $employeeUserMap = [];
            try {
                $employees = $odoo->fetchEmployees();
                foreach ($employees as $emp) {
                    if ($emp['userId'] !== null) {
                        $employeeUserMap[$emp['id']] = $emp['userId'];
                    }
                }
            } catch (\Throwable $e) {
                echo "  [5/5] Warning: could not fetch employees: {$e->getMessage()}\n";
            }
            echo "  [5/5] " . count($employeeUserMap) . " employees cached\n";

            // 5c. Save project-stage relationships
            echo "  [5/5] Saving project-stage relationships...\n";
            $psModel = new SyncProjectStageModel();
            $psModel->deleteByConfig($odooConfigId);
            $pairCount = 0;
            foreach (array_unique($projectStagePairs) as $pair) {
                [$stageOdooId, $projectOdooId] = array_map('intval', explode(':', $pair));
                $psModel->insertPair($stageOdooId, $projectOdooId, $odooConfigId);
                $pairCount++;
            }
            echo "  [5/5] {$pairCount} relationships saved\n";

            // 5d. Save stages
            echo "  [5/5] Saving stages...\n";
            $syncStageModel = new SyncStageModel();
            foreach ($allStages as $stage) {
                $syncStageModel->upsert($stage['id'], $odooConfigId, [
                    'name' => $stage['name'],
                    'sequence' => $stage['sequence'],
                ]);
            }
            echo "  [5/5] Stages saved\n";

            // 5e. Save tasks
            echo "  [5/5] Saving tasks...\n";
            $syncTaskModel = new SyncTaskModel();
            foreach ($allTasks as $task) {
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

                $syncTaskModel->upsert($task['id'], $odooConfigId, [
                    'name' => $task['name'],
                    'description' => $task['description'] ?? '',
                    'stageOdooId' => $stageId,
                    'assigneeIds' => json_encode($assignees),
                    'priority' => $task['priority'] ?? '0',
                    'deadline' => $deadline,
                    'color' => $color,
                    'projectOdooId' => $task['projectOdooId'],
                ]);
            }
            echo "  [5/5] " . count($allTasks) . " tasks saved\n";

            // 5f. Save timesheets (con employee→user cache)
            echo "  [5/5] Saving timesheets...\n";
            $syncTimesheetModel = new SyncTimesheetModel();
            $totalTs = count($allTimesheets);
            $tsSaved = 0;
            foreach ($allTimesheets as $ts) {
                $userId = null;
                if (!empty($ts['employee_id']) && $ts['employee_id'] !== false) {
                    $empId = is_array($ts['employee_id']) ? (int) $ts['employee_id'][0] : (int) $ts['employee_id'];
                    $userId = $employeeUserMap[$empId] ?? $empId;
                }
                if ($userId === null && !empty($ts['user_id']) && $ts['user_id'] !== false) {
                    $userId = is_array($ts['user_id']) ? (int) $ts['user_id'][0] : (int) $ts['user_id'];
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
                    'userOdooId' => $userId,
                    'taskOdooId' => (int) ($ts['taskOdooId'] ?? 0),
                ];
                if (!empty($ts['employee_id']) && $ts['employee_id'] !== false) {
                    $empId = is_array($ts['employee_id']) ? (int) $ts['employee_id'][0] : (int) $ts['employee_id'];
                    $tsUpsertData['employeeOdooId'] = $empId;
                }
                $syncTimesheetModel->upsert((int) $ts['id'], $odooConfigId, $tsUpsertData);
                $tsSaved++;
            }
            echo "  [5/5] {$tsSaved} timesheet entries saved\n";

            echo "  ✅ Sync complete!\n";

            // 7. Update sync state
            $syncStateModel->upsert($odooConfigId, [
                'syncing' => false,
                'lastSyncAt' => date('Y-m-d H:i:s'),
                'error' => null,
                'odooUid' => $odooUid,
            ]);
        } catch (\Throwable $err) {
            $syncStateModel->upsert($odooConfigId, [
                'syncing' => false,
                'error' => $err->getMessage(),
            ]);
            throw $err;
        }
    }
}
