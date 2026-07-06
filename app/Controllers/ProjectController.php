<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Models\ProjectModel;
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

            usort($enriched, function ($a, $b) {
                if ($a->isMine && !$b->isMine) return -1;
                if (!$a->isMine && $b->isMine) return 1;
                return strcasecmp($a->name, $b->name);
            });

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
}
