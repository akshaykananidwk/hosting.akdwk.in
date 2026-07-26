<?php
// FILE: /routes/web.php
// -------------------------------------------------------------------
// Web routes for all modules. Controllers Admin\*, Client\*, etc.
// Middleware aliases registered in index.php.
// -------------------------------------------------------------------

use App\Core\App;
use App\Core\Response;
use App\Core\Router;

return function (Router $router): void {

    // ---- Public ----
    $router->get('/', function () {
        return App::instance()->isInstalled()
            ? Response::redirect(url('login'))
            : Response::redirect(url('install/'));
    })->name('home');

    $router->get('/health', function () {
        $app = App::instance();
        $dbOk = false;
        if ($app->isInstalled()) {
            try { $dbOk = $app->make('db')->ping(); } catch (\Throwable) { $dbOk = false; }
        }
        return Response::json([
            'app' => $app->config('app.name', 'AK Cloud'), 'status' => 'ok',
            'installed' => $app->isInstalled(), 'database' => $dbOk ? 'connected' : 'not_connected',
            'time' => now(),
        ]);
    })->name('health');

    // ---- Auth (guest) ----
    $router->group(['middleware' => ['csrf']], function (Router $router) {
        $router->group(['middleware' => ['guest']], function (Router $router) {
            $router->get('/login', 'AuthController@showLogin')->name('login');
            $router->post('/login', 'AuthController@login');
            $router->get('/register', 'AuthController@showRegister')->name('register');
            $router->post('/register', 'AuthController@register');
            $router->get('/forgot', 'AuthController@showForgot')->name('forgot');
            $router->post('/forgot', 'AuthController@forgot');
            $router->get('/reset', 'AuthController@showReset')->name('reset');
            $router->post('/reset', 'AuthController@reset');
        });
        $router->get('/login/2fa', 'AuthController@show2fa');
        $router->post('/login/2fa', 'AuthController@verify2fa');
        $router->match(['GET', 'POST'], '/logout', 'AuthController@logout')->middleware('auth');

        // ---- Store / ordering (auth) ----
        $router->group(['prefix' => '/store', 'middleware' => ['maintenance']], function (Router $router) {
            $router->get('', 'OrderController@catalog')->name('store');
            $router->get('/{slug}', 'OrderController@configure');
        });
        $router->group(['prefix' => '/order', 'middleware' => ['auth']], function (Router $router) {
            $router->post('', 'OrderController@place');
            $router->get('/checkout/{invoice}', 'OrderController@checkout');
            $router->post('/pay/{invoice}', 'OrderController@pay');
            $router->get('/success/{invoice}', 'OrderController@success');
        });

        // ---- Admin ----
        $router->group(['prefix' => '/admin', 'middleware' => ['auth', 'admin']], function (Router $router) {
            $router->get('', 'Admin\\DashboardController@index')->name('admin');
            $router->get('/clients', 'Admin\\ClientController@index');
            $router->get('/clients/create', 'Admin\\ClientController@create');
            $router->post('/clients', 'Admin\\ClientController@store');
            $router->get('/clients/{id}', 'Admin\\ClientController@show');
            $router->post('/clients/{id}', 'Admin\\ClientController@update');
            $router->post('/clients/{id}/impersonate', 'Admin\\ClientController@impersonate');
            $router->get('/services', 'Admin\\ServiceController@index');
            $router->get('/services/{id}', 'Admin\\ServiceController@show');
            $router->post('/services/{id}/suspend', 'Admin\\ServiceController@suspend');
            $router->post('/services/{id}/unsuspend', 'Admin\\ServiceController@unsuspend');
            $router->post('/services/{id}/terminate', 'Admin\\ServiceController@terminate');
            $router->get('/invoices', 'Admin\\InvoiceController@index');
            $router->get('/invoices/{id}', 'Admin\\InvoiceController@show');
            $router->post('/invoices/{id}/markpaid', 'Admin\\InvoiceController@markPaid');
            $router->get('/transactions', 'Admin\\TransactionController@index');
            $router->post('/transactions/{id}/approve', 'Admin\\TransactionController@approve');
            $router->get('/tickets', 'Admin\\TicketController@index');
            $router->get('/tickets/{id}', 'Admin\\TicketController@show');
            $router->post('/tickets/{id}/reply', 'Admin\\TicketController@reply');
            $router->get('/products', 'Admin\\ProductController@index');
            $router->get('/products/create', 'Admin\\ProductController@create');
            $router->post('/products', 'Admin\\ProductController@store');
            $router->get('/products/{id}/edit', 'Admin\\ProductController@edit');
            $router->post('/products/{id}', 'Admin\\ProductController@update');
            $router->get('/coupons', 'Admin\\CouponController@index');
            $router->post('/coupons', 'Admin\\CouponController@store');
            $router->get('/domains', 'Admin\\DomainController@index');
            $router->get('/servers', 'Admin\\ServerController@index');
            $router->get('/servers/create', 'Admin\\ServerController@create');
            $router->post('/servers', 'Admin\\ServerController@store');
            $router->post('/servers/{id}/test', 'Admin\\ServerController@test');
            $router->post('/servers/{id}/import', 'Admin\\ServerController@importSites');
            $router->get('/provisioning', 'Admin\\ProvisioningController@index');
            $router->post('/provisioning/{id}/retry', 'Admin\\ProvisioningController@retry');
            $router->get('/backups', 'Admin\\BackupController@index');
            $router->post('/backups/run', 'Admin\\BackupController@run');
            $router->get('/reports', 'Admin\\ReportController@index');
            $router->get('/reports/gst', 'Admin\\ReportController@gstCsv');
            $router->get('/templates', 'Admin\\TemplateController@index');
            $router->post('/templates/{id}', 'Admin\\TemplateController@update');
            $router->get('/settings', 'Admin\\SettingController@index');
            $router->post('/settings', 'Admin\\SettingController@save');
            $router->get('/updates', 'Admin\\UpdateController@index');
            $router->post('/updates/check', 'Admin\\UpdateController@check');
            $router->post('/updates/run', 'Admin\\UpdateController@run');
            $router->get('/logs', 'Admin\\LogController@index');
        });

        // ---- Client area ----
        $router->group(['prefix' => '/client', 'middleware' => ['auth', 'client', 'maintenance']], function (Router $router) {
            $router->get('', 'Client\\DashboardController@index')->name('client');
            $router->get('/services', 'Client\\ServiceController@index');
            $router->get('/services/{id}', 'Client\\ServiceController@show');
            $router->post('/services/{id}/ssl', 'Client\\ServiceController@installSsl');
            $router->post('/services/{id}/password', 'Client\\ServiceController@changePassword');
            $router->get('/services/{id}/files', 'Client\\FileManagerController@index');
            $router->post('/services/{id}/files/save', 'Client\\FileManagerController@save');
            $router->get('/services/{id}/database', 'Client\\DatabaseController@index');
            $router->get('/domains', 'Client\\DomainController@index');
            $router->get('/invoices', 'Client\\InvoiceController@index');
            $router->get('/invoices/{id}', 'Client\\InvoiceController@show');
            $router->get('/tickets', 'Client\\TicketController@index');
            $router->post('/tickets', 'Client\\TicketController@store');
            $router->get('/tickets/{id}', 'Client\\TicketController@show');
            $router->post('/tickets/{id}/reply', 'Client\\TicketController@reply');
            $router->get('/profile', 'Client\\ProfileController@index');
            $router->post('/profile', 'Client\\ProfileController@update');
        });

        // ---- Reseller ----
        $router->group(['prefix' => '/reseller', 'middleware' => ['auth', 'reseller']], function (Router $router) {
            $router->get('', 'Reseller\\DashboardController@index')->name('reseller');
            $router->get('/clients', 'Reseller\\ClientController@index');
            $router->get('/pricing', 'Reseller\\PricingController@index');
            $router->post('/pricing', 'Reseller\\PricingController@save');
        });
    });

    // ---- Payment webhooks (no CSRF — signature verified) ----
    $router->post('/webhook/razorpay', 'OrderController@razorpayWebhook');
};
