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

    // ---- Authenticated REST API (Module 20) — token via 'api' middleware. ----
    $router->group(['prefix' => '/api/v1', 'middleware' => ['api']], function (Router $router) {

        // Clients.
        $router->get('/clients', 'Api\\ClientController@index')->name('api.clients.index');
        $router->get('/clients/{id}', 'Api\\ClientController@show')->name('api.clients.show');

        // Services.
        $router->get('/services', 'Api\\ServiceController@index')->name('api.services.index');
        $router->get('/services/{id}', 'Api\\ServiceController@show')->name('api.services.show');
        $router->post('/services/{id}/suspend', 'Api\\ServiceController@suspend')->name('api.services.suspend');
        $router->post('/services/{id}/unsuspend', 'Api\\ServiceController@unsuspend')->name('api.services.unsuspend');

        // Invoices.
        $router->get('/invoices', 'Api\\InvoiceController@index')->name('api.invoices.index');
        $router->get('/invoices/{id}', 'Api\\InvoiceController@show')->name('api.invoices.show');

        // WhatsApp.
        $router->post('/whatsapp/send', 'Api\\WhatsAppController@send')->name('api.whatsapp.send');
    });
};
