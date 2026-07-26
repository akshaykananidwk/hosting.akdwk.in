<?php
// FILE: /routes/web.php
// -------------------------------------------------------------------
// Web routes. પછીના modules આ file માં routes ઉમેરશે (auth, admin,
// client, reseller). હાલ Core ને testable રાખવા માટે health + home.
// -------------------------------------------------------------------

use App\Core\App;
use App\Core\Response;
use App\Core\Router;

return function (Router $router): void {

    // Home — plain landing so the framework is verifiable end-to-end.
    $router->get('/', function () {
        $name = e(config('app.name', 'AK Cloud'));
        $ver = e((string) (json_decode((string) @file_get_contents(base_path('version.json')), true)['version'] ?? '0.1.0'));
        $html = <<<HTML
        <!doctype html><html lang="gu"><head><meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>{$name}</title>
        <style>body{font-family:system-ui,Arial,sans-serif;background:#0f172a;color:#e2e8f0;
        display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
        .card{text-align:center}.b{color:#38bdf8}</style></head>
        <body><div class="card"><h1>☁️ {$name}</h1>
        <p>aaPanel-powered SaaS Hosting Platform — <span class="b">v{$ver}</span></p>
        <p>Core framework ચાલુ છે ✅</p></div></body></html>
        HTML;
        return Response::make($html);
    })->name('home');

    // Health check — used by the updater and monitoring.
    $router->get('/health', function () {
        $app = App::instance();
        $dbOk = false;
        if ($app->isInstalled()) {
            try {
                $dbOk = $app->make('db')->ping();
            } catch (\Throwable) {
                $dbOk = false;
            }
        }
        return Response::json([
            'app' => $app->config('app.name', 'AK Cloud'),
            'status' => 'ok',
            'installed' => $app->isInstalled(),
            'database' => $dbOk ? 'connected' : 'not_connected',
            'time' => now(),
        ]);
    })->name('health');
};
