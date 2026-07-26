<?php
// FILE: /index.php
// -------------------------------------------------------------------
// Front controller: every request passes through here. Loads the
// autoloader and helpers, boots the App, starts the session and
// dispatches to the Router.
// -------------------------------------------------------------------

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

define('AKC_START', microtime(true));

$basePath = __DIR__;

// 1) Autoloader (no Composer required).
require $basePath . '/app/Core/Autoloader.php';
Autoloader::register($basePath);

// 2) Global helper functions.
require $basePath . '/app/Helpers/functions.php';

// 3) Boot the framework (config, env, error handling, services).
$app = App::boot($basePath);

// 4) Secure session.
Session::start();

// 5) Installer gate: if not installed yet and the installer is present,
//    send the visitor to /install (Module 1).
$request = Request::capture();
if (!$app->isInstalled()
    && is_file($basePath . '/install/index.php')
    && !str_starts_with($request->path(), '/install')) {
    Response::redirect('/install/')->send();
    return;
}

// 6) Router + route definitions.
$router = new Router();

// Middleware aliases.
$router->aliasMiddleware('auth', \App\Middleware\AuthMiddleware::class);
$router->aliasMiddleware('guest', \App\Middleware\GuestMiddleware::class);
$router->aliasMiddleware('csrf', \App\Middleware\CsrfMiddleware::class);
$router->aliasMiddleware('admin', \App\Middleware\AdminMiddleware::class);
$router->aliasMiddleware('reseller', \App\Middleware\ResellerMiddleware::class);
$router->aliasMiddleware('client', \App\Middleware\ClientMiddleware::class);
$router->aliasMiddleware('maintenance', \App\Middleware\MaintenanceMiddleware::class);
$router->aliasMiddleware('api', \App\Middleware\ApiAuthMiddleware::class);

$web = require $basePath . '/routes/web.php';
$web($router);

$api = require $basePath . '/routes/api.php';
$api($router);

// 7) Dispatch and send response (with security headers).
$response = $router->dispatch($request);
$response->withSecurityHeaders()->send();
