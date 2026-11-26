<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
$dataPath = getenv('CLOTHEASE_DATA_PATH');
$maintenancePath = $dataPath !== false && $dataPath !== ''
    ? rtrim($dataPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'storage/framework/maintenance.php'
    : __DIR__.'/../storage/framework/maintenance.php';

if (file_exists($maintenance = $maintenancePath)) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
