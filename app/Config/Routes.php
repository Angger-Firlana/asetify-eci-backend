<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->post('auth/login', 'AuthController::login');

    $routes->group('', ['filter' => 'api-auth'], static function (RouteCollection $routes): void {
        $routes->post('auth/logout', 'AuthController::logout');
        $routes->get('auth/me', 'AuthController::me');

        $routes->get('assets/check-sn', 'AssetController::checkSerialNumber');
        $routes->post('assets', 'AssetController::create');
        $routes->get('assets', 'AssetController::index');
        $routes->get('assets/(:num)', 'AssetController::show/$1');
        $routes->put('assets/(:num)', 'AssetController::update/$1');
        $routes->get('assets/(:num)/photos', 'AssetController::photos/$1');
        $routes->post('assets/(:num)/photos', 'AssetController::addPhotos/$1');
        $routes->delete('assets/(:num)/photos/(:num)', 'AssetController::deletePhoto/$1/$2');
        $routes->get('assets/(:num)/audit-logs', 'HistoryController::assetAuditLogs/$1');
        $routes->get('assets/(:num)/download-photo/(:num)', 'AssetController::downloadPhoto/$1/$2');

        $routes->get('masters/asset-categories', 'MasterDataController::assetCategories');
        $routes->get('masters/brands', 'MasterDataController::brands');
        $routes->get('masters/locations', 'MasterDataController::locations');

        $routes->post('uploads/photos', 'UploadController::photo');
        $routes->post('scan-logs', 'ScanLogController::create');
        $routes->get('scan-logs', 'HistoryController::scanLogs');
        $routes->get('audit-logs', 'HistoryController::globalAuditLogs');
        $routes->get('dashboard/summary', 'DashboardController::summary');
    });
});
