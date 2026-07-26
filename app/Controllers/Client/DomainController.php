<?php
// FILE: /app/Controllers/Client/DomainController.php
// -------------------------------------------------------------------
// MODULE 10 — Client domains list. ફક્ત current client ના domains.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class DomainController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $client   = db()->table('clients')->where('user_id', auth()->id())->first();
        $clientId = (int) ($client['id'] ?? 0);

        $domains = $clientId
            ? db()->table('domains')->where('client_id', $clientId)->orderBy('expiry_date', 'asc')->orderBy('id', 'desc')->get()
            : [];

        return $this->view('client.domains.index', ['domains' => $domains]);
    }
}
