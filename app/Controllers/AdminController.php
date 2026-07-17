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

    // ─── STATS ─────────────────────────────────────────────────

    /**
     * GET /api/admin/stats
     * KPIs globales del sistema (solo admin).
     */
    public function stats()
    {
        $db = \Config\Database::connect();

        // Total de proyectos desde la base local (syncproject)
        $totalProjects = $db->table('public.syncproject')->countAllResults();

        // Total de usuarios registrados
        $totalUsers = $db->table('"User"')->countAllResults();

        // Total de tareas desde la base local
        $totalTasks = $db->table('public.synctask')->countAllResults();

        // Total de partes de hora desde la base local
        $totalTimesheets = $db->table('public.synctimesheet')->countAllResults();

        // Configuraciones Odoo activas
        $totalOdooConfigs = $db->table('"OdooConfig"')->countAllResults();

        return $this->respondSuccess([
            'stats' => [
                'totalProjects'   => (int) $totalProjects,
                'totalUsers'      => (int) $totalUsers,
                'totalTasks'      => (int) $totalTasks,
                'totalTimesheets' => (int) $totalTimesheets,
                'totalOdooConfigs'=> (int) $totalOdooConfigs,
            ],
        ]);
    }

    // ─── TIMESHEETS ────────────────────────────────────────────

    /**
     * GET /api/admin/timesheets
     * Lista todos los timesheets de la BD local con filtros.
     * Query params: ?period=day|week|month|year&employeeId=&projectId=
     */
    public function listTimesheets()
    {
        $period = $this->request->getGet('period') ?? 'week';
        $employeeId = $this->request->getGet('employeeId');
        $projectId = $this->request->getGet('projectId');
        $page = (int) ($this->request->getGet('page') ?? 0);
        $pageSize = (int) ($this->request->getGet('pageSize') ?? 20);

        $page = max(0, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = $page * $pageSize;

        $db = \Config\Database::connect();

        $since = null;
        switch ($period) {
            case 'day':
                $since = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $since = date('Y-m-d 00:00:00', strtotime('monday this week'));
                break;
            case 'month':
                $since = date('Y-m-01 00:00:00');
                break;
            case 'year':
                $since = date('Y-01-01 00:00:00');
                break;
        }

        // ── WHERE clauses ──
        $where = 'WHERE 1=1';

        if ($since) {
            $where .= ' AND ts."date" >= ' . $db->escape($since);
        }

        if ($employeeId) {
            $where .= ' AND ts."employeeOdooId" = ' . (int) $employeeId;
        }

        if ($projectId) {
            $where .= ' AND pr."odooId" = ' . (int) $projectId;
        }

        $from = 'FROM public.synctimesheet ts
            LEFT JOIN public.synctask tk ON tk."odooId" = ts."taskOdooId" AND tk."odooConfigId" = ts."odooConfigId"
            LEFT JOIN public.syncproject pr ON pr."odooId" = tk."projectOdooId" AND pr."odooConfigId" = ts."odooConfigId"';

        // ── Total count ──
        $countSql = "SELECT COUNT(*) as total $from $where";
        $countResult = $db->query($countSql)->getRow();
        $total = (int) ($countResult->total ?? 0);
        $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1;

        // ── Total hours (without pagination) ──
        $hoursSql = "SELECT COALESCE(SUM(ts.\"unitAmount\"), 0) as totalHours $from $where";
        $hoursResult = $db->query($hoursSql)->getRow();
        $totalHours = (float) ($hoursResult->totalhours ?? 0);

        // ── Data query with pagination ──
        $sql = "SELECT ts.id, ts.\"odooId\" as odooId, ts.name as description, ts.\"unitAmount\" as hours, ts.date, ts.\"taskOdooId\" as taskId, ts.\"employeeOdooId\" as employeeId, ts.\"userOdooId\" as userId, ts.\"odooConfigId\" as configId, tk.name as taskName, pr.\"odooId\" as projectId, pr.name as projectName $from $where ORDER BY ts.\"date\" DESC, ts.\"unitAmount\" DESC LIMIT $pageSize OFFSET $offset";

        $rows = $db->query($sql)->getResult();

        // Recoger todos los employeeOdooId únicos para resolver nombres
        $empIds = [];
        foreach ($rows as $r) {
            if ($r->employeeid) {
                $empIds[(int) $r->employeeid] = true;
            }
        }

        // Resolver nombres desde catálogo (empleados)
        $empNames = [];
        if (!empty($empIds)) {
            $keys = implode(',', array_map(fn($id) => $db->escape((string) $id), array_keys($empIds)));
            $catSql = 'SELECT DISTINCT ci."key", ci.value FROM "CatalogItem" ci INNER JOIN "Catalog" c ON c.id = ci."catalogId" WHERE c.name = \'employees\' AND ci."key" IN (' . $keys . ')';
            $catalogItems = $db->query($catSql)->getResult();
            foreach ($catalogItems as $ci) {
                $empNames[$ci->key] = $ci->value;
            }
        }

        $timesheets = array_map(function ($r) use ($empNames) {
            $empId = $r->employeeid ? (int) $r->employeeid : null;
            return [
                'id'           => $r->id ?? $r->odooId,
                'description'  => $r->description ?? '',
                'hours'        => (float) ($r->hours ?? 0),
                'date'         => $r->date,
                'taskId'       => $r->taskid ? (int) $r->taskid : null,
                'taskName'     => $r->taskname ?? ('Task #' . $r->taskid),
                'projectId'    => $r->projectid ? (int) $r->projectid : null,
                'projectName'  => $r->projectname ?? ('Project #' . $r->projectid),
                'employeeId'   => $empId,
                'employeeName' => $empId ? ($empNames[(string) $empId] ?? ('Employee #' . $empId)) : 'Unknown',
            ];
        }, $rows);

        return $this->respondSuccess([
            'timesheets' => $timesheets,
            'total'      => $total,
            'totalPages' => $totalPages,
            'page'       => $page,
            'pageSize'   => $pageSize,
            'totalHours' => $totalHours,
        ]);
    }

    /**
     * GET /api/admin/timesheets/export?format=xlsx|csv&period=...&employeeId=...&projectId=...
     * Exporta timesheets filtrados como archivo Excel o CSV.
     */
    public function exportTimesheets()
    {
        $format = $this->request->getGet('format') ?? 'xlsx';
        $period = $this->request->getGet('period') ?? 'week';
        $employeeId = $this->request->getGet('employeeId');
        $projectId = $this->request->getGet('projectId');

        $db = \Config\Database::connect();

        $since = null;
        switch ($period) {
            case 'day':
                $since = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $since = date('Y-m-d 00:00:00', strtotime('monday this week'));
                break;
            case 'month':
                $since = date('Y-m-01 00:00:00');
                break;
            case 'year':
                $since = date('Y-01-01 00:00:00');
                break;
        }

        $where = 'WHERE 1=1';
        if ($since) {
            $where .= ' AND ts."date" >= ' . $db->escape($since);
        }
        if ($employeeId) {
            $where .= ' AND ts."employeeOdooId" = ' . (int) $employeeId;
        }
        if ($projectId) {
            $where .= ' AND pr."odooId" = ' . (int) $projectId;
        }

        $from = 'FROM public.synctimesheet ts
            LEFT JOIN public.synctask tk ON tk."odooId" = ts."taskOdooId" AND tk."odooConfigId" = ts."odooConfigId"
            LEFT JOIN public.syncproject pr ON pr."odooId" = tk."projectOdooId" AND pr."odooConfigId" = ts."odooConfigId"';

        $sql = "SELECT ts.id, ts.name as description, ts.\"unitAmount\" as hours, ts.date, ts.\"taskOdooId\" as taskId, tk.name as taskName, pr.name as projectName, ts.\"employeeOdooId\" as employeeId $from $where ORDER BY ts.\"date\" DESC, ts.\"unitAmount\" DESC";

        $rows = $db->query($sql)->getResult();

        // Resolver nombres de empleados
        $empIds = [];
        foreach ($rows as $r) {
            if ($r->employeeid) {
                $empIds[(int) $r->employeeid] = true;
            }
        }
        $empNames = [];
        if (!empty($empIds)) {
            $keys = implode(',', array_map(fn($id) => $db->escape((string) $id), array_keys($empIds)));
            $catSql = 'SELECT DISTINCT ci."key", ci.value FROM "CatalogItem" ci INNER JOIN "Catalog" c ON c.id = ci."catalogId" WHERE c.name = \'employees\' AND ci."key" IN (' . $keys . ')';
            $catalogItems = $db->query($catSql)->getResult();
            foreach ($catalogItems as $ci) {
                $empNames[$ci->key] = $ci->value;
            }
        }

        // Mapear datos
        $data = array_map(function ($r) use ($empNames) {
            $empId = $r->employeeid ? (int) $r->employeeid : null;
            return [
                'Fecha'      => $r->date ? date('d/m/Y', strtotime($r->date)) : '-',
                'Empleado'   => $empId ? ($empNames[(string) $empId] ?? ('Employee #' . $empId)) : 'Unknown',
                'Proyecto'   => $r->projectname ?? ('Project #' . $r->taskid),
                'Tarea'      => $r->taskname ?? ('Task #' . $r->taskid),
                'Descripción' => $r->description ?? '',
                'Horas'      => (float) ($r->hours ?? 0),
            ];
        }, $rows);

        if ($format === 'csv') {
            return $this->exportCsv($data);
        }

        return $this->exportXlsx($data);
    }

    /**
     * Genera y descarga un archivo CSV.
     */
    private function exportCsv(array $data): \CodeIgniter\HTTP\ResponseInterface
    {
        $filename = 'timesheets_' . date('Ymd_His') . '.csv';

        // Escribir CSV en un buffer
        $output = fopen('php://temp', 'r+');
        // BOM para Excel (UTF-8)
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]), ';');
        }

        // Rows
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $this->response
            ->setStatusCode(200)
            ->setContentType('text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    /**
     * Genera y descarga un archivo Excel (XLSX) usando PhpSpreadsheet.
     */
    private function exportXlsx(array $data): \CodeIgniter\HTTP\ResponseInterface
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Timesheets');

        // Estilo para headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '171717']],
        ];

        // Headers
        $colIdx = 1;
        if (!empty($data)) {
            foreach (array_keys($data[0]) as $header) {
                $cell = $sheet->getCell([$colIdx, 1]);
                $cell->setValue($header);
                $sheet->getStyle([$colIdx, 1])->applyFromArray($headerStyle);
                $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
                $colIdx++;
            }
        }

        // Rows
        $rowNum = 2;
        foreach ($data as $row) {
            $colIdx = 1;
            foreach ($row as $value) {
                $cell = $sheet->getCell([$colIdx, $rowNum]);
                $cell->setValue($value);
                $colIdx++;
            }
            $rowNum++;
        }

        $filename = 'timesheets_' . date('Ymd_His') . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $xlsx = ob_get_clean();

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($xlsx);
    }

    /**
     * GET /api/admin/timesheets/filters
     * Devuelve listas de empleados y proyectos disponibles para los filtros.
     */
    public function timesheetFilters()
    {
        $db = \Config\Database::connect();

        // Todos los empleados desde el catálogo (no solo los que tienen timesheets)
        $employeeList = [];
        $catRows = $db->query('SELECT DISTINCT ci."key", ci.value FROM "CatalogItem" ci INNER JOIN "Catalog" c ON c.id = ci."catalogId" WHERE c.name = \'employees\' ORDER BY ci.value ASC')->getResult();
        $seen = [];
        foreach ($catRows as $r) {
            $id = (int) $r->key;
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $employeeList[] = [
                'id'   => $id,
                'name' => $r->value ?? ('Employee #' . $id),
            ];
        }

        // Todos los proyectos desde syncproject (no solo los que tienen timesheets)
        $projRows = $db->query('SELECT "odooId", name FROM public.syncproject WHERE "odooId" IS NOT NULL ORDER BY name ASC')->getResult();
        $seenProj = [];
        $projectList = [];
        foreach ($projRows as $p) {
            $id = (int) $p->odooId;
            if (isset($seenProj[$id])) continue;
            $seenProj[$id] = true;
            $projectList[] = [
                'id'   => $id,
                'name' => $p->name ?? ('Project #' . $id),
            ];
        }

        return $this->respondSuccess([
            'employees' => $employeeList,
            'projects' => $projectList,
        ]);
    }

    /**
     * GET /api/admin/timesheets/report
     * Reporte agrupado de partes de horas con filtros de fecha y agrupación.
     *
     * Query params:
     *   dateFrom    (YYYY-MM-DD)
     *   dateTo      (YYYY-MM-DD)
     *   groupBy     (employee_project_task | project_employee_task)
     *   employeeId  (int) — filtrar por empleado
     *   projectId   (int) — filtrar por proyecto
     */
    public function report()
    {
        $dateFrom   = $this->request->getGet('dateFrom');
        $dateTo     = $this->request->getGet('dateTo');
        $groupBy    = $this->request->getGet('groupBy') ?? 'employee_project_task';
        $format     = $this->request->getGet('format'); // xlsx or csv
        $employeeId = $this->request->getGet('employeeId');
        $projectId  = $this->request->getGet('projectId');

        $db = \Config\Database::connect();

        // ── WHERE ──
        $where = 'WHERE 1=1';
        if ($dateFrom) {
            $where .= ' AND ts."date" >= ' . $db->escape($dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $where .= ' AND ts."date" <= ' . $db->escape($dateTo . ' 23:59:59');
        }
        if ($employeeId) {
            $where .= ' AND ts."employeeOdooId" = ' . (int) $employeeId;
        }
        if ($projectId) {
            $where .= ' AND pr."odooId" = ' . (int) $projectId;
        }

        $from = 'FROM public.synctimesheet ts
            INNER JOIN public.synctask tk ON tk."odooId" = ts."taskOdooId" AND tk."odooConfigId" = ts."odooConfigId"
            INNER JOIN public.syncproject pr ON pr."odooId" = tk."projectOdooId" AND pr."odooConfigId" = ts."odooConfigId"';

        // ── Fetch all rows ──
        $sql = "SELECT ts.id, ts.name as description, ts.\"unitAmount\" as hours, ts.date,
                       ts.\"taskOdooId\" as taskId, tk.name as taskName,
                       pr.\"odooId\" as projectId, pr.name as projectName,
                       ts.\"employeeOdooId\" as employeeId, ts.\"userOdooId\" as userId
                $from $where
                ORDER BY ts.date ASC, ts.\"unitAmount\" DESC";

        $rows = $db->query($sql)->getResult();

        // ── Resolver nombres de empleados ──
        $empIds = [];
        foreach ($rows as $r) {
            if ($r->employeeid) $empIds[(int) $r->employeeid] = true;
        }
        $empNames = [];
        if (!empty($empIds)) {
            $keys = implode(',', array_map(fn($id) => $db->escape((string) $id), array_keys($empIds)));
            $catSql = 'SELECT DISTINCT ci."key", ci.value FROM "CatalogItem" ci INNER JOIN "Catalog" c ON c.id = ci."catalogId" WHERE c.name = \'employees\' AND ci."key" IN (' . $keys . ')';
            $catalogItems = $db->query($catSql)->getResult();
            foreach ($catalogItems as $ci) {
                $empNames[$ci->key] = $ci->value;
            }
        }

        $getEmployeeName = function ($empId) use ($empNames) {
            if (!$empId) return 'Unknown';
            return $empNames[(string) $empId] ?? ('Employee #' . $empId);
        };

        // ── Build entries list ──
        $entries = [];
        foreach ($rows as $r) {
            $eid = $r->employeeid ? (int) $r->employeeid : 0;
            $pid = $r->projectid ? (int) $r->projectid : 0;
            $tid = $r->taskid ? (int) $r->taskid : 0;

            $entries[] = [
                'employeeId'   => $eid,
                'employeeName' => $getEmployeeName($eid),
                'projectId'    => $pid,
                'projectName'  => $r->projectname ?? ('Project #' . $pid),
                'taskId'       => $tid,
                'taskName'     => $r->taskname ?? ('Task #' . $tid),
                'date'         => $r->date,
                'description'  => $r->description ?? '',
                'hours'        => (float) ($r->hours ?? 0),
            ];
        }

        // ── Group data ──
        $totalHours = 0;
        $groups = [];

        if ($groupBy === 'project_employee_task') {
            // Group by Project → Employee → Task
            $projectMap = [];
            foreach ($entries as $e) {
                $totalHours += $e['hours'];
                $pk = (string) $e['projectId'];

                if (!isset($projectMap[$pk])) {
                    $projectMap[$pk] = [
                        'name'        => $e['projectName'],
                        'totalHours'  => 0,
                        'employees'   => [],
                    ];
                }
                $projectMap[$pk]['totalHours'] += $e['hours'];

                $ek = $pk . ':' . $e['employeeId'];
                if (!isset($projectMap[$pk]['employees'][$ek])) {
                    $projectMap[$pk]['employees'][$ek] = [
                        'name'       => $e['employeeName'],
                        'totalHours' => 0,
                        'tasks'      => [],
                    ];
                }
                $projectMap[$pk]['employees'][$ek]['totalHours'] += $e['hours'];

                $tk = $ek . ':' . $e['taskId'];
                if (!isset($projectMap[$pk]['employees'][$ek]['tasks'][$tk])) {
                    $projectMap[$pk]['employees'][$ek]['tasks'][$tk] = [
                        'name'       => $e['taskName'],
                        'totalHours' => 0,
                        'entries'    => [],
                    ];
                }
                $projectMap[$pk]['employees'][$ek]['tasks'][$tk]['totalHours'] += $e['hours'];
                $projectMap[$pk]['employees'][$ek]['tasks'][$tk]['entries'][] = [
                    'date'        => $e['date'],
                    'description' => $e['description'],
                    'hours'       => $e['hours'],
                ];
            }

            // Sort projects by name, flatten employee map
            usort($projectMap, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            foreach ($projectMap as &$proj) {
                $proj['employees'] = array_values($proj['employees']);
                usort($proj['employees'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
                foreach ($proj['employees'] as &$emp) {
                    $emp['tasks'] = array_values($emp['tasks']);
                    usort($emp['tasks'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
                }
            }
            $groups = $projectMap;

        } else {
            // Default: Group by Employee → Project → Task
            $employeeMap = [];
            foreach ($entries as $e) {
                $totalHours += $e['hours'];
                $ek = (string) $e['employeeId'];

                if (!isset($employeeMap[$ek])) {
                    $employeeMap[$ek] = [
                        'name'       => $e['employeeName'],
                        'totalHours' => 0,
                        'projects'   => [],
                    ];
                }
                $employeeMap[$ek]['totalHours'] += $e['hours'];

                $pk = $ek . ':' . $e['projectId'];
                if (!isset($employeeMap[$ek]['projects'][$pk])) {
                    $employeeMap[$ek]['projects'][$pk] = [
                        'name'       => $e['projectName'],
                        'totalHours' => 0,
                        'tasks'      => [],
                    ];
                }
                $employeeMap[$ek]['projects'][$pk]['totalHours'] += $e['hours'];

                $tk = $pk . ':' . $e['taskId'];
                if (!isset($employeeMap[$ek]['projects'][$pk]['tasks'][$tk])) {
                    $employeeMap[$ek]['projects'][$pk]['tasks'][$tk] = [
                        'name'       => $e['taskName'],
                        'totalHours' => 0,
                        'entries'    => [],
                    ];
                }
                $employeeMap[$ek]['projects'][$pk]['tasks'][$tk]['totalHours'] += $e['hours'];
                $employeeMap[$ek]['projects'][$pk]['tasks'][$tk]['entries'][] = [
                    'date'        => $e['date'],
                    'description' => $e['description'],
                    'hours'       => $e['hours'],
                ];
            }

            // Sort employees by name, flatten project map
            usort($employeeMap, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            foreach ($employeeMap as &$emp) {
                $emp['projects'] = array_values($emp['projects']);
                usort($emp['projects'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
                foreach ($emp['projects'] as &$proj) {
                    $proj['tasks'] = array_values($proj['tasks']);
                    usort($proj['tasks'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
                }
            }
            $groups = $employeeMap;
        }

        // ── Export format? ──
        if ($format === 'xlsx' || $format === 'csv') {
            if ($format === 'csv') {
                return $this->exportCsv([]);
            }
            return $this->exportReportXlsx($groups, $totalHours, $dateFrom, $dateTo, $groupBy);
        }

        return $this->respondSuccess([
            'groups'     => $groups,
            'totalHours' => $totalHours,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'groupBy'    => $groupBy,
        ]);
    }

    /**
     * Convierte horas decimales a formato HH:MM.
     * Ej: 2.5 → "2:30", 89.5 → "89:30"
     */
    private function formatHoursHHMM(float $hours): string
    {
        $totalMinutes = (int) round($hours * 60);
        $h = intdiv($totalMinutes, 60);
        $m = $totalMinutes % 60;
        return sprintf('%d:%02d', $h, $m);
    }

    /**
     * Exporta el reporte agrupado a XLSX con formato jerárquico.
     */
    private function exportReportXlsx(array $groups, float $totalHours, ?string $dateFrom, ?string $dateTo, string $groupBy): \CodeIgniter\HTTP\ResponseInterface
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');

        // Estilos
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '171717']],
        ];
        $dateRangeStyle = [
            'font' => ['size' => 11, 'color' => ['rgb' => '666666']],
        ];
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '171717']],
        ];
        $groupStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '171717']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
        ];
        $subGroupStyle = [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '404040']],
        ];
        $entryFont = [
            'font' => ['size' => 10, 'color' => ['rgb' => '333333']],
        ];

        $isEmployeeGroup = ($groupBy === 'employee_project_task');

        // ── Row 1: Título ──
        $sheet->getCell('A1')->setValue($isEmployeeGroup ? 'Reporte de partes de horas' : 'Reporte de partes de horas');
        $sheet->getStyle('A1')->applyFromArray($titleStyle);

        // ── Row 2: Rango de fechas ──
        $dateRangeStr = '';
        if ($dateFrom && $dateTo) {
            $dateRangeStr = date('d/m/Y', strtotime($dateFrom)) . ' - ' . date('d/m/Y', strtotime($dateTo));
        } elseif ($dateFrom) {
            $dateRangeStr = 'Desde ' . date('d/m/Y', strtotime($dateFrom));
        } elseif ($dateTo) {
            $dateRangeStr = 'Hasta ' . date('d/m/Y', strtotime($dateTo));
        }
        $sheet->getCell('A2')->setValue($dateRangeStr);
        $sheet->getStyle('A2')->applyFromArray($dateRangeStyle);

        // ── Row 4: Headers ──
        $headerRow = 4;
        $sheet->getCell('A' . $headerRow)->setValue('Fecha');
        $sheet->getCell('B' . $headerRow)->setValue('Descripción');
        $sheet->getCell('C' . $headerRow)->setValue('Horas');
        $sheet->getStyle('A' . $headerRow . ':C' . $headerRow)->applyFromArray($headerStyle);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getColumnDimension('C')->setWidth(12);

        $row = $headerRow + 1;

        foreach ($groups as $group) {
            if ($isEmployeeGroup) {
                // Group = Employee → Project → Task
                $employeeName = $group['name'];
                $employeeTotal = $group['totalHours'];

                // Employee header row
                $sheet->getCell('A' . $row)->setValue($employeeName);
                $sheet->getCell('C' . $row)->setValue($this->formatHoursHHMM($employeeTotal));
                $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($groupStyle);
                $row++;

                foreach ($group['projects'] as $project) {
                    $projectName = $project['name'];
                    $projectTotal = $project['totalHours'];

                    // Project header row
                    $sheet->getCell('A' . $row)->setValue('  ' . $projectName);
                    $sheet->getCell('C' . $row)->setValue($this->formatHoursHHMM($projectTotal));
                    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($subGroupStyle);
                    $row++;

                    foreach ($project['tasks'] as $task) {
                        foreach ($task['entries'] as $entry) {
                            $sheet->getCell('A' . $row)->setValue($entry['date'] ? date('d/m/Y', strtotime($entry['date'])) : '-');
                            $sheet->getCell('B' . $row)->setValue($entry['description'] ?: $task['name']);
                            $sheet->getCell('C' . $row)->setValue($this->formatHoursHHMM($entry['hours']));
                            $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($entryFont);
                            $row++;
                        }
                    }
                }
            } else {
                // Group = Project → Employee → Task
                $projectName = $group['name'];
                $projectTotal = $group['totalHours'];

                // Project header row
                $sheet->getCell('A' . $row)->setValue($projectName);
                $sheet->getCell('C' . $row)->setValue($this->formatHoursHHMM($projectTotal));
                $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($groupStyle);
                $row++;

                foreach ($group['employees'] as $employee) {
                    $employeeName = $employee['name'];
                    $employeeTotal = $employee['totalHours'];

                    // Employee header row
                    $sheet->getCell('A' . $row)->setValue('  ' . $employeeName);
                    $sheet->getCell('C' . $row)->setValue($this->formatHoursHHMM($employeeTotal));
                    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($subGroupStyle);
                    $row++;

                    foreach ($employee['tasks'] as $task) {
                        foreach ($task['entries'] as $entry) {
                            $sheet->getCell('A' . $row)->setValue($entry['date'] ? date('d/m/Y', strtotime($entry['date'])) : '-');
                            $sheet->getCell('B' . $row)->setValue($entry['description'] ?: $task['name']);
                            $sheet->getCell('C' . $row)->setValue($this->formatHoursHHMM($entry['hours']));
                            $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($entryFont);
                            $row++;
                        }
                    }
                }
            }
        }

        // Total row
        $row++;
        $sheet->getCell('A' . $row)->setValue('Total general');
        $sheet->getCell('C' . $row)->setValue($this->formatHoursHHMM($totalHours));
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($groupStyle);

        $filename = 'reporte_horas_' . date('Ymd_His') . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $xlsx = ob_get_clean();

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($xlsx);
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
