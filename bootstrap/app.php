<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsurePermission;

$application = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class, 'permission'=>EnsurePermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// The Windows test package keeps its environment, database, logs, sessions,
// backups, and compiled views outside the replaceable application directory.
$dataPath = getenv('CLOTHEASE_DATA_PATH');
if ($dataPath !== false && $dataPath !== '') {
    $application->useEnvironmentPath($dataPath);
    $application->useStoragePath(rtrim($dataPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'storage');
}

return $application;
