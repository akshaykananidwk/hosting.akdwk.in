<?php
// FILE: /app/Controllers/Reseller/ClientController.php
// -------------------------------------------------------------------
// 🟢 MODULE 13 — RESELLER area: list the clients that belong to this
// reseller (clients.reseller_id = current reseller id).
// -------------------------------------------------------------------

namespace App\Controllers\Reseller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class ClientController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $reseller = db()->table('resellers')
            ->where('user_id', (int) auth()->id())
            ->first();

        $resellerId = (int) ($reseller['id'] ?? 0);

        $page = max(1, (int) $request->query('page', 1));

        $clients = $resellerId
            ? db()->table('clients')
                ->where('reseller_id', $resellerId)
                ->orderBy('created_at', 'desc')
                ->paginate($page, 20)
            : ['data' => [], 'total' => 0, 'per_page' => 20, 'current_page' => 1, 'last_page' => 1];

        return $this->view('reseller.clients', [
            'reseller' => $reseller,
            'clients'  => $clients,
        ]);
    }
}
