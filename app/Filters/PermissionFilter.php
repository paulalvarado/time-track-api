<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro de permisos para rutas.
 *
 * Uso en routes: ['filter' => 'permission:permiso.key']
 * Ejemplo: ['filter' => 'permission:tasks.create']
 *
 * También soporta múltiples permisos (OR):
 * ['filter' => 'permission:tasks.create,admin.manage_roles']
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Si no hay userId, no está autenticado
        $userId = $request->userId ?? null;
        if (!$userId) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'Unauthorized']);
        }

        // Si no hay permisos definidos en el filtro, denegar
        if (empty($arguments)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['error' => 'Forbidden: no permission specified']);
        }

        $permissions = $request->userPermissions ?? [];
        $required = $arguments;

        // Soportar múltiples permisos separados por coma (OR — cualquiera basta)
        $requiredList = explode(',', $required[0]);

        $hasPermission = false;
        foreach ($requiredList as $perm) {
            $perm = trim($perm);
            if (in_array($perm, $permissions, true)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['error' => 'Forbidden: insufficient permissions']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
