<?php
// FILE: /routes/api.php
// -------------------------------------------------------------------
// REST API routes (Module 20 આને વિસ્તારશે). બધા /api/v1 prefix નીચે.
// -------------------------------------------------------------------

use App\Core\Response;
use App\Core\Router;

return function (Router $router): void {

    $router->group(['prefix' => '/api/v1'], function (Router $router) {

        // Ping endpoint — token વગર public health.
        $router->get('/ping', function () {
            return Response::json(['pong' => true, 'time' => now()]);
        })->name('api.ping');

        $router->get('/version', function () {
            $data = json_decode((string) @file_get_contents(base_path('version.json')), true) ?: [];
            return Response::json($data);
        })->name('api.version');
    });
};
