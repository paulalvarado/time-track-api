<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\RoleModel;
use App\Models\PermissionModel;
use App\Models\UserRoleModel;

class AdminController extends BaseController
{
    // ─── USERS ─────────────────────────────────────────────────

    /**
     * GET /api/admin/users
     * Lista todos los usuarios del sistema con sus roles.
     */
    public function listUsers()
    {
        $userModel = new UserModel();
        $users = $userModel->findAll();

        $userRoleModel = new UserRoleModel();
        $result = array_map(function ($user) use ($userRoleModel) {
            $roles = $userRoleModel->getRolesForUser($user->id);
            return [
                'id'        => $user->id,
                'email'     => $user->email,
                'name'      => $user->name,
                'createdAt' => $user->createdAt,
                'roles'     => array_map(fn($r) => [
                    'id'   => $r->id,
                    'name' => $r->name,
                ], $roles),
            ];
        }, $users);

        return $this->respondSuccess(['users' => $result]);
    }

    /**
     * GET /api/admin/users/(:any)
     * Detalle de un usuario con sus roles y permisos efectivos.
     */
    public function getUser(string $userId)
    {
        $userModel = new UserModel();
        $user = $userModel->findById($userId);
        if (!$user) {
            return $this->respondNotFound('User not found');
        }

        $userRoleModel = new UserRoleModel();
        $roles = $userRoleModel->getRolesForUser($userId);
        $permissions = $userRoleModel->getPermissionsForUser($userId);

        return $this->respondSuccess([
            'user' => [
                'id'        => $user->id,
                'email'     => $user->email,
                'name'      => $user->name,
                'createdAt' => $user->createdAt,
                'roles'     => array_map(fn($r) => [
                    'id'   => $r->id,
                    'name' => $r->name,
                    'isSystem' => (bool) $r->isSystem,
                ], $roles),
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * POST /api/admin/users/(:any)/roles
     * Asigna roles a un usuario. Body: { roleIds: [...] }
     */
    public function setUserRoles(string $userId)
    {
        $data = $this->getJsonInput();
        $roleIds = $data['roleIds'] ?? [];

        $userModel = new UserModel();
        $user = $userModel->findById($userId);
        if (!$user) {
            return $this->respondNotFound('User not found');
        }

        $userRoleModel = new UserRoleModel();

        // Remove all existing roles
        $existingRoles = $userRoleModel->findByUser($userId);
        foreach ($existingRoles as $ur) {
            // No permitir quitar el rol admin si es el último admin
            $roleModel = new RoleModel();
            $role = $roleModel->find($ur->roleId);
            if ($role && $role->name === 'admin' && !in_array($ur->roleId, $roleIds)) {
                // Check if there are other admins
                $adminCount = $this->countAdminUsers();
                if ($adminCount <= 1) {
                    return $this->respondError('Cannot remove the last admin role from the system');
                }
            }
            $userRoleModel->removeRole($userId, $ur->roleId);
        }

        // Assign new roles
        foreach ($roleIds as $roleId) {
            $userRoleModel->assignRole($userId, $roleId);
        }

        return $this->respondSuccess(['ok' => true]);
    }

    // ─── ROLES ─────────────────────────────────────────────────

    /**
     * GET /api/admin/roles
     * Lista todos los roles con su conteo de permisos.
     */
    public function listRoles()
    {
        $roleModel = new RoleModel();
        $roles = $roleModel->findAllWithPermissionCount();

        return $this->respondSuccess(['roles' => $roles]);
    }

    /**
     * POST /api/admin/roles
     * Crea un nuevo rol. Body: { name, description }
     */
    public function createRole()
    {
        $data = $this->getJsonInput();
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($name)) {
            return $this->respondError('Role name is required');
        }

        $roleModel = new RoleModel();
        $existing = $roleModel->findByName($name);
        if ($existing) {
            return $this->respondError('A role with this name already exists', 409);
        }

        $roleModel->insert([
            'id'          => 'c' . bin2hex(random_bytes(12)),
            'name'        => $name,
            'description' => $description,
            'isSystem'    => false,
            'createdAt'   => date('Y-m-d H:i:s'),
            'updatedAt'   => date('Y-m-d H:i:s'),
        ]);

        $role = $roleModel->findByName($name);
        return $this->respondSuccess(['role' => $role], 'Role created', 201);
    }

    /**
     * PUT /api/admin/roles/(:any)
     * Actualiza un rol (nombre, descripción).
     * Los roles de sistema (isSystem=true) solo pueden cambiar descripción.
     */
    public function updateRole(string $roleId)
    {
        $roleModel = new RoleModel();
        $role = $roleModel->find($roleId);
        if (!$role) {
            return $this->respondNotFound('Role not found');
        }

        $data = $this->getJsonInput();
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');

        $updateData = [];
        if (!empty($name)) {
            // No permitir renombrar roles de sistema
            if ((bool) $role->isSystem && $name !== $role->name) {
                return $this->respondError('Cannot rename a system role');
            }
            $updateData['name'] = $name;
        }
        if (!empty($description) || isset($data['description'])) {
            $updateData['description'] = $description;
        }

        if (!empty($updateData)) {
            $updateData['updatedAt'] = date('Y-m-d H:i:s');
            $roleModel->update($roleId, $updateData);
        }

        return $this->respondSuccess(['role' => $roleModel->find($roleId)]);
    }

    /**
     * DELETE /api/admin/roles/(:any)
     * Elimina un rol. No se pueden eliminar roles de sistema.
     */
    public function deleteRole(string $roleId)
    {
        $roleModel = new RoleModel();
        $role = $roleModel->find($roleId);
        if (!$role) {
            return $this->respondNotFound('Role not found');
        }

        if ((bool) $role->isSystem) {
            return $this->respondError('Cannot delete a system role');
        }

        $roleModel->delete($roleId);
        return $this->respondSuccess(['ok' => true]);
    }

    // ─── PERMISSIONS ───────────────────────────────────────────

    /**
     * GET /api/admin/permissions
     * Lista todos los permisos disponibles agrupados.
     */
    public function listPermissions()
    {
        $permModel = new PermissionModel();
        $grouped = $permModel->findAllGrouped();

        return $this->respondSuccess(['permissions' => $grouped]);
    }

    /**
     * GET /api/admin/roles/(:any)/permissions
     * Obtiene los permisos asignados a un rol.
     */
    public function getRolePermissions(string $roleId)
    {
        $roleModel = new RoleModel();
        $role = $roleModel->find($roleId);
        if (!$role) {
            return $this->respondNotFound('Role not found');
        }

        $permissions = $roleModel->getPermissions($roleId);
        return $this->respondSuccess([
            'role'        => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * PUT /api/admin/roles/(:any)/permissions
     * Reemplaza todos los permisos de un rol.
     * Body: { permissionIds: [...] }
     * Los roles de sistema no pueden modificarse (admin).
     */
    public function setRolePermissions(string $roleId)
    {
        $roleModel = new RoleModel();
        $role = $roleModel->find($roleId);
        if (!$role) {
            return $this->respondNotFound('Role not found');
        }

        // No permitir modificar permisos del rol admin (isSystem)
        if ((bool) $role->isSystem && $role->name === 'admin') {
            return $this->respondError('Cannot modify permissions of the admin role');
        }

        $data = $this->getJsonInput();
        $permissionIds = $data['permissionIds'] ?? [];

        $roleModel->syncPermissions($roleId, $permissionIds);

        return $this->respondSuccess(['ok' => true]);
    }

    // ─── HELPERS ───────────────────────────────────────────────

    private function countAdminUsers(): int
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"UserRole" ur');
        $builder->join('"Role" r', 'r."id" = ur."roleId"');
        $builder->where('r."name"', 'admin');
        return $builder->countAllResults();
    }
}
