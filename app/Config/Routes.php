<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

// Catch-all para solicitudes de preflight de CORS
$routes->options('(:any)', static function () {
    return '';
});

// ─── Auth ─────────────────────────────────────────────────────
$routes->post('/api/auth/register', 'AuthController::register');
$routes->post('/api/auth/login', 'AuthController::login');
$routes->post('/api/auth/logout', 'AuthController::logout');
$routes->put('/api/auth/profile', 'AuthController::updateProfile', ['filter' => ['auth']]);
$routes->get('/api/auth/me', 'AuthController::me', ['filter' => ['auth']]);

// ─── Odoo Config ──────────────────────────────────────────────
$routes->post('/api/odoo/config', 'OdooConfigController::save', ['filter' => ['auth', 'permission:odoo.manage_config']]);
$routes->get('/api/odoo/config', 'OdooConfigController::get', ['filter' => ['auth', 'permission:odoo.view_config']]);
$routes->post('/api/odoo/sync', 'OdooConfigController::sync', ['filter' => ['auth', 'permission:odoo.manage_config']]);
$routes->post('/api/odoo/test', 'OdooConfigController::test', ['filter' => ['auth', 'permission:odoo.test_connection']]);
$routes->post('/api/odoo/gemini-key', 'OdooConfigController::saveGeminiKey', ['filter' => ['auth', 'permission:odoo.manage_ai']]);
$routes->post('/api/odoo/ai-config', 'OdooConfigController::saveAiConfig', ['filter' => ['auth', 'permission:odoo.manage_ai']]);
$routes->post('/api/odoo/ai-test', 'OdooConfigController::testAiConfig', ['filter' => ['auth', 'permission:odoo.manage_ai']]);
$routes->get('/api/odoo/employees', 'OdooConfigController::listEmployees', ['filter' => ['auth', 'permission:odoo.view_employees']]);
$routes->post('/api/odoo/employee-preference', 'OdooConfigController::saveEmployeePreference', ['filter' => ['auth', 'permission:odoo.manage_employee']]);
$routes->get('/api/odoo/employee-preference', 'OdooConfigController::getEmployeePreference', ['filter' => ['auth', 'permission:odoo.manage_employee']]);
$routes->get('/api/odoo/timesheets/all', 'OdooConfigController::timesheetsAll', ['filter' => ['auth', 'permission:odoo.view_timesheets_all']]);
$routes->get('/api/odoo/catalogs', 'CatalogController::list', ['filter' => ['auth', 'permission:odoo.view_catalogs']]);
$routes->get('/api/odoo/catalogs/(:any)', 'CatalogController::getByName/$1', ['filter' => ['auth', 'permission:odoo.view_catalogs']]);

// ─── Projects (legacy) ────────────────────────────────────────
$routes->get('/api/projects/count', 'ProjectController::count', ['filter' => ['auth', 'permission:projects.count']]);
$routes->get('/api/projects', 'ProjectController::list', ['filter' => ['auth', 'permission:projects.list']]);
$routes->get('/api/projects/(:num)/tasks', 'TaskController::listByProject/$1', ['filter' => ['auth', 'permission:projects.view_tasks']]);
$routes->get('/api/projects/(:num)/tasks/(:num)/timesheets', 'TimesheetController::listByTask/$1/$2', ['filter' => ['auth', 'permission:timesheets.list']]);
$routes->put('/api/projects/(:num)/tasks/(:num)/timesheets/(:num)', 'TimesheetController::update/$1/$2/$3', ['filter' => ['auth', 'permission:timesheets.update']]);
$routes->post('/api/projects/(:num)/tasks/(:num)/timesheets/batch', 'TimesheetController::batchCreate/$1/$2', ['filter' => ['auth', 'permission:timesheets.create']]);

// ─── Sync endpoints ───────────────────────────────────────────
$routes->get('/api/sync/projects', 'SyncController::listProjects', ['filter' => ['auth', 'permission:projects.list']]);
$routes->get('/api/sync/project-users', 'SyncController::listProjectUsers', ['filter' => ['auth', 'permission:projects.list']]);
$routes->post('/api/sync/projects/(:num)/track', 'SyncController::trackProject/$1', ['filter' => ['auth', 'permission:projects.track']]);
$routes->get('/api/sync/projects/(:num)/stages', 'SyncController::listStages/$1', ['filter' => ['auth', 'permission:projects.view_stages']]);
$routes->get('/api/sync/projects/(:num)/tasks', 'SyncController::listTasks/$1', ['filter' => ['auth', 'permission:tasks.list']]);
$routes->get('/api/sync/projects/(:num)/tasks/(:num)', 'SyncController::getTask/$1/$2', ['filter' => ['auth', 'permission:tasks.view']]);
$routes->post('/api/sync/projects/(:num)/tasks', 'SyncController::createTask/$1', ['filter' => ['auth', 'permission:tasks.create']]);
$routes->put('/api/sync/projects/(:num)/tasks/(:num)', 'SyncController::updateTask/$1/$2', ['filter' => ['auth', 'permission:tasks.update']]);
$routes->delete('/api/sync/projects/(:num)/tasks/(:num)', 'SyncController::deleteTask/$1/$2', ['filter' => ['auth', 'permission:tasks.delete']]);
$routes->get('/api/sync/projects/(:num)/tasks/(:num)/timesheets', 'SyncController::listTimesheets/$1/$2', ['filter' => ['auth', 'permission:timesheets.list']]);
$routes->post('/api/sync/trigger', 'SyncController::triggerSync', ['filter' => ['auth', 'permission:timesheets.view_hours']]);
$routes->get('/api/sync/progress', 'SyncController::syncProgress', ['filter' => ['auth', 'permission:timesheets.view_hours']]);
$routes->get('/api/sync/hours', 'SyncController::totalHours', ['filter' => ['auth', 'permission:timesheets.view_hours']]);
$routes->get('/api/sync/hours-by-employee/(:num)', 'SyncController::hoursByEmployee/$1', ['filter' => ['auth', 'permission:timesheets.view_hours_by_employee']]);
$routes->get('/api/sync/status', 'SyncController::status', ['filter' => ['auth']]);

// ─── User Metadata ────────────────────────────────────────────
$routes->get('/api/user/metadata', 'UserMetadataController::index', ['filter' => ['auth', 'permission:metadata.manage']]);
$routes->get('/api/user/metadata/(:any)', 'UserMetadataController::get/$1', ['filter' => ['auth', 'permission:metadata.manage']]);
$routes->put('/api/user/metadata/(:any)', 'UserMetadataController::set/$1', ['filter' => ['auth', 'permission:metadata.manage']]);
$routes->delete('/api/user/metadata/(:any)', 'UserMetadataController::delete/$1', ['filter' => ['auth', 'permission:metadata.manage']]);

// ─── AI ───────────────────────────────────────────────────────
$routes->post('/api/ai/transcribe-timesheet', 'AiController::transcribeTimesheet', ['filter' => ['auth', 'permission:ai.transcribe_timesheet']]);
$routes->post('/api/ai/transcribe-task', 'AiController::transcribeTask', ['filter' => ['auth', 'permission:ai.transcribe_task']]);

// ─── Admin ────────────────────────────────────────────────────
$routes->get('/api/admin/users', 'AdminController::listUsers', ['filter' => ['auth', 'permission:admin.manage_users']]);
$routes->get('/api/admin/users/(:any)', 'AdminController::getUser/$1', ['filter' => ['auth', 'permission:admin.manage_users']]);
$routes->post('/api/admin/users/(:any)/roles', 'AdminController::setUserRoles/$1', ['filter' => ['auth', 'permission:admin.manage_users']]);

$routes->get('/api/admin/roles', 'AdminController::listRoles', ['filter' => ['auth', 'permission:admin.manage_roles']]);
$routes->post('/api/admin/roles', 'AdminController::createRole', ['filter' => ['auth', 'permission:admin.manage_roles']]);
$routes->put('/api/admin/roles/(:any)', 'AdminController::updateRole/$1', ['filter' => ['auth', 'permission:admin.manage_roles']]);
$routes->delete('/api/admin/roles/(:any)', 'AdminController::deleteRole/$1', ['filter' => ['auth', 'permission:admin.manage_roles']]);

$routes->get('/api/admin/stats', 'AdminController::stats', ['filter' => ['auth', 'permission:admin.manage_users']]);
$routes->get('/api/admin/timesheets/export', 'AdminController::exportTimesheets', ['filter' => ['auth', 'permission:odoo.view_timesheets_all']]);
$routes->get('/api/admin/timesheets', 'AdminController::listTimesheets', ['filter' => ['auth', 'permission:odoo.view_timesheets_all']]);
$routes->get('/api/admin/timesheets/report', 'AdminController::report', ['filter' => ['auth', 'permission:odoo.view_timesheets_all']]);
$routes->get('/api/admin/timesheets/filters', 'AdminController::timesheetFilters', ['filter' => ['auth', 'permission:odoo.view_timesheets_all']]);

$routes->get('/api/admin/permissions', 'AdminController::listPermissions', ['filter' => ['auth', 'permission:admin.manage_permissions']]);
$routes->get('/api/admin/roles/(:any)/permissions', 'AdminController::getRolePermissions/$1', ['filter' => ['auth', 'permission:admin.manage_permissions']]);
$routes->put('/api/admin/roles/(:any)/permissions', 'AdminController::setRolePermissions/$1', ['filter' => ['auth', 'permission:admin.manage_permissions']]);

// ─── Health check ─────────────────────────────────────────────
$routes->get('/api/health', static function () {
    return service('response')->setStatusCode(200)->setJSON(['status' => 'ok']);
});
