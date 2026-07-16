<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolesAndPermissions extends Seeder
{
    public function run()
    {
        // ── 1. Definir todos los permisos del sistema ──────────────
        $permissions = [
            // Auth / Perfil
            ['key' => 'auth.view_profile',    'name' => 'Ver perfil propio',            'group' => 'Auth'],
            ['key' => 'auth.update_profile',  'name' => 'Actualizar perfil propio',      'group' => 'Auth'],

            // Conexión Odoo
            ['key' => 'odoo.view_config',       'name' => 'Ver configuración Odoo',         'group' => 'Odoo'],
            ['key' => 'odoo.manage_config',     'name' => 'Guardar/configurar Odoo',        'group' => 'Odoo'],
            ['key' => 'odoo.test_connection',   'name' => 'Probar conexión Odoo',           'group' => 'Odoo'],
            ['key' => 'odoo.manage_ai',         'name' => 'Configurar proveedores IA',      'group' => 'Odoo'],
            ['key' => 'odoo.view_employees',    'name' => 'Listar empleados',               'group' => 'Odoo'],
            ['key' => 'odoo.manage_employee',   'name' => 'Seleccionar empleado preferido', 'group' => 'Odoo'],
            ['key' => 'odoo.view_catalogs',     'name' => 'Ver catálogos',                  'group' => 'Odoo'],
            ['key' => 'odoo.view_timesheets_all', 'name' => 'Ver todos los timesheets',     'group' => 'Odoo'],

            // Proyectos
            ['key' => 'projects.list',          'name' => 'Listar proyectos',               'group' => 'Proyectos'],
            ['key' => 'projects.count',         'name' => 'Contar proyectos',               'group' => 'Proyectos'],
            ['key' => 'projects.view_tasks',    'name' => 'Ver tareas (Odoo directo)',      'group' => 'Proyectos'],
            ['key' => 'projects.track',         'name' => 'Trackear proyecto no-propio',    'group' => 'Proyectos'],
            ['key' => 'projects.view_stages',   'name' => 'Ver etapas de proyecto',         'group' => 'Proyectos'],

            // Tareas
            ['key' => 'tasks.list',             'name' => 'Listar tareas (local)',          'group' => 'Tareas'],
            ['key' => 'tasks.view',             'name' => 'Ver detalle de tarea',           'group' => 'Tareas'],
            ['key' => 'tasks.create',           'name' => 'Crear tarea en Odoo',            'group' => 'Tareas'],
            ['key' => 'tasks.update',           'name' => 'Editar tarea en Odoo',           'group' => 'Tareas'],
            ['key' => 'tasks.delete',           'name' => 'Eliminar tarea en Odoo',         'group' => 'Tareas'],

            // Timesheets
            ['key' => 'timesheets.list',        'name' => 'Listar timesheets',              'group' => 'Timesheets'],
            ['key' => 'timesheets.create',      'name' => 'Crear timesheets (batch)',       'group' => 'Timesheets'],
            ['key' => 'timesheets.update',      'name' => 'Editar timesheet en Odoo',       'group' => 'Timesheets'],
            ['key' => 'timesheets.view_hours',  'name' => 'Ver total horas propias',        'group' => 'Timesheets'],
            ['key' => 'timesheets.view_hours_by_employee', 'name' => 'Ver horas por empleado', 'group' => 'Timesheets'],

            // Metadata
            ['key' => 'metadata.manage',        'name' => 'Gestionar metadata propia',      'group' => 'Metadata'],

            // IA
            ['key' => 'ai.transcribe_timesheet', 'name' => 'Transcribir parte de hora',     'group' => 'IA'],
            ['key' => 'ai.transcribe_task',      'name' => 'Transcribir tarea',             'group' => 'IA'],

            // Admin
            ['key' => 'admin.manage_users',      'name' => 'Gestionar usuarios',            'group' => 'Admin'],
            ['key' => 'admin.manage_roles',       'name' => 'Gestionar roles',              'group' => 'Admin'],
            ['key' => 'admin.manage_permissions', 'name' => 'Asignar permisos a roles',     'group' => 'Admin'],
        ];

        // ── 2. Insertar permisos ─────────────────────────────────
        $permIds = [];
        foreach ($permissions as $p) {
            $existing = $this->db->table('"Permission"')
                ->where('key', $p['key'])
                ->get()
                ->getRow();

            if ($existing) {
                $permIds[$p['key']] = $existing->id;
                echo "  Permission already exists: {$p['key']}\n";
            } else {
                $id = 'c' . bin2hex(random_bytes(12));
                $this->db->table('"Permission"')->insert([
                    'id'          => $id,
                    'key'         => $p['key'],
                    'name'        => $p['name'],
                    'group'       => $p['group'],
                    'description' => null,
                    'createdAt'   => date('Y-m-d H:i:s'),
                ]);
                $permIds[$p['key']] = $id;
                echo "  Created permission: {$p['key']}\n";
            }
        }

        // ── 3. Crear rol "admin" (isSystem=true) ──────────────────
        $adminRole = $this->db->table('"Role"')->where('name', 'admin')->get()->getRow();
        if (!$adminRole) {
            $adminRoleId = 'c' . bin2hex(random_bytes(12));
            $this->db->table('"Role"')->insert([
                'id'          => $adminRoleId,
                'name'        => 'admin',
                'description' => 'Administrador del sistema — acceso total a todas las funcionalidades.',
                'isSystem'    => true,
                'createdAt'   => date('Y-m-d H:i:s'),
                'updatedAt'   => date('Y-m-d H:i:s'),
            ]);
            echo "  Created role: admin (system)\n";
        } else {
            $adminRoleId = $adminRole->id;
            echo "  Role already exists: admin\n";
        }

        // ── 4. Asignar TODOS los permisos al admin ────────────────
        foreach ($permIds as $key => $permId) {
            $exists = $this->db->table('"RolePermission"')
                ->where('"roleId"', $adminRoleId)
                ->where('"permissionId"', $permId)
                ->get()
                ->getRow();
            if (!$exists) {
                $this->db->table('"RolePermission"')->insert([
                    'id'           => 'c' . bin2hex(random_bytes(12)),
                    'roleId'       => $adminRoleId,
                    'permissionId' => $permId,
                    'createdAt'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
        echo "  Assigned all permissions to admin\n";

        // ── 5. Crear rol "user" (isSystem=false, modificable) ─────
        $userRole = $this->db->table('"Role"')->where('name', 'user')->get()->getRow();
        if (!$userRole) {
            $userRoleId = 'c' . bin2hex(random_bytes(12));
            $this->db->table('"Role"')->insert([
                'id'          => $userRoleId,
                'name'        => 'user',
                'description' => 'Usuario estándar — acceso a funcionalidades básicas del sistema.',
                'isSystem'    => false,
                'createdAt'   => date('Y-m-d H:i:s'),
                'updatedAt'   => date('Y-m-d H:i:s'),
            ]);
            echo "  Created role: user\n";
        } else {
            $userRoleId = $userRole->id;
            echo "  Role already exists: user\n";
        }

        // ── 6. Asignar permisos RESTRINGIDOS al rol user ──────────
        // El rol user NO tendrá permisos de admin ni algunos críticos
        $userPermissionKeys = [
            'auth.view_profile',
            'auth.update_profile',
            'odoo.view_config',
            'odoo.manage_config',
            'odoo.test_connection',
            'odoo.manage_ai',
            'odoo.view_employees',
            'odoo.manage_employee',
            'odoo.view_catalogs',
            'projects.list',
            'projects.count',
            'projects.view_tasks',
            'projects.track',
            'projects.view_stages',
            'tasks.list',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'timesheets.list',
            'timesheets.create',
            'timesheets.update',
            'timesheets.view_hours',
            'timesheets.view_hours_by_employee',
            'metadata.manage',
            'ai.transcribe_timesheet',
            'ai.transcribe_task',
        ];

        foreach ($userPermissionKeys as $key) {
            if (!isset($permIds[$key])) continue;
            $exists = $this->db->table('"RolePermission"')
                ->where('"roleId"', $userRoleId)
                ->where('"permissionId"', $permIds[$key])
                ->get()
                ->getRow();
            if (!$exists) {
                $this->db->table('"RolePermission"')->insert([
                    'id'           => 'c' . bin2hex(random_bytes(12)),
                    'roleId'       => $userRoleId,
                    'permissionId' => $permIds[$key],
                    'createdAt'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
        echo "  Assigned restricted permissions to user\n";

        // ── 7. Crear usuario admin desde variables de entorno ─────
        $adminEmail = env('ADMIN_EMAIL', 'admin@timetrack.app');
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');
        $adminName = env('ADMIN_NAME', 'Administrator');

        $existingUser = $this->db->table('"User"')->where('email', $adminEmail)->get()->getRow();
        if (!$existingUser) {
            $adminUserId = 'c' . bin2hex(random_bytes(12));
            $this->db->table('"User"')->insert([
                'id'        => $adminUserId,
                'email'     => $adminEmail,
                'name'      => $adminName,
                'password'  => password_hash($adminPassword, PASSWORD_BCRYPT),
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
            ]);
            echo "  Created admin user: {$adminEmail}\n";
        } else {
            $adminUserId = $existingUser->id;
            echo "  Admin user already exists: {$adminEmail}\n";

            // Ensure password is up-to-date from env
            $this->db->table('"User"')
                ->where('id', $adminUserId)
                ->update([
                    'password'  => password_hash($adminPassword, PASSWORD_BCRYPT),
                    'updatedAt' => date('Y-m-d H:i:s'),
                ]);
            echo "  Updated admin password from env\n";
        }

        // ── 8. Asignar rol admin al usuario admin ─────────────────
        $exists = $this->db->table('"UserRole"')
            ->where('"userId"', $adminUserId)
            ->where('"roleId"', $adminRoleId)
            ->get()
            ->getRow();
        if (!$exists) {
            $this->db->table('"UserRole"')->insert([
                'id'        => 'c' . bin2hex(random_bytes(12)),
                'userId'    => $adminUserId,
                'roleId'    => $adminRoleId,
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
            echo "  Assigned admin role to admin user\n";
        } else {
            echo "  Admin user already has admin role\n";
        }

        echo "\n✅ RolesAndPermissions seed completed.\n";
    }
}
