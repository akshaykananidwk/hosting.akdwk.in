<?php
// FILE: /app/Controllers/Client/ServiceController.php
// -------------------------------------------------------------------
// MODULE 10 — Client services. List, detail (credentials decrypt),
// SSL install queue અને site password change. દરેક action પર
// ownership ફરજિયાત — client પોતાની જ service જોઈ/બદલી શકે.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class ServiceController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $clientId = $this->clientId();
        $services = $clientId
            ? db()->table('services')->where('client_id', $clientId)->orderBy('id', 'desc')->get()
            : [];

        return $this->view('client.services.index', ['services' => $services]);
    }

    public function show(Request $request, string $id = ''): Response
    {
        $service = $this->ownedService($id);
        $details = db()->table('service_details')->where('service_id', (int) $service['id'])->first();

        // Decrypt secrets for display (encrypted at rest).
        $ftpPass  = ($details && !empty($details['ftp_pass_enc'])) ? (decrypt($details['ftp_pass_enc']) ?? '') : '';
        $dbPass   = ($details && !empty($details['db_pass_enc'])) ? (decrypt($details['db_pass_enc']) ?? '') : '';
        $sitePass = !empty($service['password_enc']) ? (decrypt($service['password_enc']) ?? '') : '';

        return $this->view('client.services.show', [
            'service'  => $service,
            'details'  => $details,
            'ftpPass'  => $ftpPass,
            'dbPass'   => $dbPass,
            'sitePass' => $sitePass,
        ]);
    }

    public function installSsl(Request $request, string $id = ''): Response
    {
        $service = $this->ownedService($id);
        (new \App\Services\ProvisioningService())->enqueue((int) $service['id'], 'install_ssl');
        audit('client.service.install_ssl', 'service', (int) $service['id']);

        return back_with('success', 'SSL install queue માં ઉમેર્યું. થોડી વારમાં લાગુ થશે.');
    }

    public function changePassword(Request $request, string $id = ''): Response
    {
        $service = $this->ownedService($id);
        $this->validate($request, ['password' => 'required|min:8']);

        db()->table('services')->where('id', (int) $service['id'])->update([
            'password_enc' => encrypt((string) $request->input('password')),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        audit('client.service.password', 'service', (int) $service['id']);

        return back_with('success', 'પાસવર્ડ બદલાયો.');
    }

    // ---------------------------------------------------------------
    // Helpers — tenant/ownership guards
    // ---------------------------------------------------------------

    private function clientId(): int
    {
        $client = db()->table('clients')->where('user_id', auth()->id())->first();
        return (int) ($client['id'] ?? 0);
    }

    /**
     * Fetch a service by id and ensure it belongs to the current client.
     * Aborts 403 otherwise (never leaks other clients' data).
     */
    private function ownedService(string $id): array
    {
        $clientId = $this->clientId();
        $service  = db()->table('services')->where('id', (int) $id)->first();
        $this->authorize($clientId > 0 && $service !== null && (int) $service['client_id'] === $clientId, 403);
        /** @var array $service */
        return $service;
    }
}
