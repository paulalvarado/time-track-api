<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

// Auth
$routes->post('/api/auth/register', 'AuthController::register');
$routes->post('/api/auth/login', 'AuthController::login');
$routes->post('/api/auth/logout', 'AuthController::logout');
$routes->put('/api/auth/profile', 'AuthController::updateProfile', ['filter' => 'auth']);
$routes->get('/api/auth/me', 'AuthController::me', ['filter' => 'auth']);

// Odoo Config
$routes->post('/api/odoo/config', 'OdooConfigController::save', ['filter' => 'auth']);
$routes->get('/api/odoo/config', 'OdooConfigController::get', ['filter' => 'auth']);
$routes->post('/api/odoo/test', 'OdooConfigController::test', ['filter' => 'auth']);
$routes->post('/api/odoo/gemini-key', 'OdooConfigController::saveGeminiKey', ['filter' => 'auth']);
$routes->get('/api/odoo/catalogs', 'CatalogController::list', ['filter' => 'auth']);

// Projects (legacy)
$routes->get('/api/projects', 'ProjectController::list', ['filter' => 'auth']);
$routes->get('/api/projects/(:num)/tasks', 'TaskController::listByProject/$1', ['filter' => 'auth']);
$routes->get('/api/projects/(:num)/tasks/(:num)/timesheets', 'TimesheetController::listByTask/$1/$2', ['filter' => 'auth']);
$routes->put('/api/projects/(:num)/tasks/(:num)/timesheets/(:num)', 'TimesheetController::update/$1/$2/$3', ['filter' => 'auth']);
$routes->post('/api/projects/(:num)/tasks/(:num)/timesheets/batch', 'TimesheetController::batchCreate/$1/$2', ['filter' => 'auth']);

// Sync endpoints
$routes->get('/api/sync/projects', 'SyncController::listProjects', ['filter' => 'auth']);
$routes->get('/api/sync/projects/(:num)/stages', 'SyncController::listStages/$1', ['filter' => 'auth']);
$routes->get('/api/sync/projects/(:num)/tasks', 'SyncController::listTasks/$1', ['filter' => 'auth']);
$routes->get('/api/sync/projects/(:num)/tasks/(:num)', 'SyncController::getTask/$1/$2', ['filter' => 'auth']);
$routes->get('/api/sync/projects/(:num)/tasks/(:num)/timesheets', 'SyncController::listTimesheets/$1/$2', ['filter' => 'auth']);
$routes->get('/api/sync/hours', 'SyncController::totalHours', ['filter' => 'auth']);
$routes->get('/api/sync/status', 'SyncController::status', ['filter' => 'auth']);

// AI
$routes->post('/api/ai/transcribe-timesheet', 'AiController::transcribeTimesheet', ['filter' => 'auth']);

// Health check
$routes->get('/api/health', static function () {
    return service('response')->setStatusCode(200)->setJSON(['status' => 'ok']);
});
