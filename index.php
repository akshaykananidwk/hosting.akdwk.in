<?php
// FILE: /index.php
// -------------------------------------------------------------------
// Front controller — બધી requests અહીંથી પસાર થાય. Autoloader +
// helpers load કરે, App boot કરે, session શરૂ કરે અને Router ને
// dispatch કરે.
// -------------------------------------------------------------------

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

define('AKC_START', microtime(true));

$basePath = __DIR__;

// 1) Autoloader (Composer વગર).
require $basePath . '/app/Core/Autoloader.php';
Autoloader::register($basePath);

// 2) Global helper functions.
require $basePath . '/app/Helpers/functions.php';

// 3) Boot the framework (config, env, error handling, services).
$app = App::boot($basePath);

// 4) Secure session.
Session::start();

// 5) Installer gate — જો install થયું ન હોય અને installer હાજર હોય તો
//    /install પર મોકલો (Module 1 આ installer બનાવશે).
$request = Request::capture();
if (!$app->isInstalled()
    && is_file($basePath . '/install/index.php')
    && !str_starts_with($request->path(), '/install')) {
    Response::redirect('/install/')->send();
    return;
}

// 6) Router + route definitions.
$router = new Router();

$web = require $basePath . '/routes/web.php';
$web($router);

$api = require $basePath . '/routes/api.php';
$api($router);

// 7) Dispatch and send response (with security headers).
$response = $router->dispatch($request);
$response->withSecurityHeaders()->send();
