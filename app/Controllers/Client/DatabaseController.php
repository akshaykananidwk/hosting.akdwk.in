<?php
// FILE: /app/Controllers/Client/DatabaseController.php
// -------------------------------------------------------------------
// MODULE 10 — Client database info. service_details માંથી DB name/user
// અને decrypted password બતાવે + phpMyAdmin/Adminer link. Ownership
// ફરજિયાત.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class DatabaseController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $service = $this->ownedService($id);
        $details = db()->table('service_details')->where('service_id', (int) $service['id'])->first();

        $dbPass = ($details && !empty($details['db_pass_enc'])) ? (decrypt($details['db_pass_enc']) ?? '') : '';

        $appUrl     = rtrim((string) settings('general.app_url', config('app.url', '')), '/');
        $adminerUrl = $appUrl !== '' ? $appUrl . '/adminer' : '';

        return $this->view('client.database.index', [
            'service'    => $service,
            'details'    => $details,
            'dbPass'     => $dbPass,
            'adminerUrl' => $adminerUrl,
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function clientId(): int
    {
        $client = db()->table('clients')->where('user_id', auth()->id())->first();
        return (int) ($client['id'] ?? 0);
    }

    private function ownedService(string $id): array
    {
        $clientId = $this->clientId();
        $service  = db()->table('services')->where('id', (int) $id)->first();
        $this->authorize($clientId > 0 && $service !== null && (int) $service['client_id'] === $clientId, 403);
        /** @var array $service */
        return $service;
    }
}
