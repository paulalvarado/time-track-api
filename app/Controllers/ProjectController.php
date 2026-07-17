<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Models\ProjectModel;
use App\Models\SyncStateModel;
use App\Services\OdooService;

class ProjectController extends BaseController
{
    public function list()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['projects' => [], 'odooConnected' => false]);
        }

        try {
            $odoo = new OdooService([
                'url' => $config->url,
                'dbName' => $config->dbName,
                'username' => $config->username,
                'apiKey' => $config->apiKey,
            ]);

            $odooProjects = $odoo->fetchProjects();
            $odooUid = $odoo->getOdooUid();

            $projectModel = new ProjectModel();
            $projects = $projectModel->upsertMany($userId, array_map(function ($p) {
                return [
                    'odooId' => $p['id'],
                    'name' => $p['name'],
                    'odooUserId' => (!empty($p['user_id']) && is_array($p['user_id'])) ? (int) $p['user_id'][0] : null,
                    'color' => $p['color'] ?? null,
                ];
            }, $odooProjects));

            $enriched = array_map(function ($p) use ($odooUid) {
                $p->isMine = ($p->odooUserId !== null && $p->odooUserId === $odooUid);
                return $p;
            }, $projects);

            // Admin: orden alfabético sin priorizar "mis proyectos"
            if ($this->request->isAdmin ?? false) {
                usort($enriched, function ($a, $b) {
                    return strcasecmp($a->name, $b->name);
                });
            } else {
                usort($enriched, function ($a, $b) {
                    if ($a->isMine && !$b->isMine) return -1;
                    if (!$a->isMine && $b->isMine) return 1;
                    return strcasecmp($a->name, $b->name);
                });
            }

            return $this->respondSuccess(['projects' => $enriched, 'odooConnected' => true]);
        } catch (\Exception $e) {
            $projectModel = new ProjectModel();
            $localProjects = $projectModel->findByUserId($userId);
            $enriched = array_map(function ($p) {
                $p->isMine = false;
                return $p;
            }, $localProjects);

            return $this->respondSuccess([
                'projects' => $enriched,
                'odooConnected' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function count()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        // Get the user's Odoo UID from sync state to count assigned projects
        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);

        $projectModel = new ProjectModel();

        if ($config) {
            $syncStateModel = new \App\Models\SyncStateModel();
            $syncState = $syncStateModel->findByConfigId($config->id);

            if ($syncState && $syncState->odooUid) {
                $total = $projectModel->countByOdooUserId($userId, (int) $syncState->odooUid);
            } else {
                $total = $projectModel->countByUserId($userId);
            }
        } else {
            $total = $projectModel->countByUserId($userId);
        }

        return $this->respondSuccess(['total' => $total]);
    }
}
