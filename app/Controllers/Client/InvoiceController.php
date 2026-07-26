<?php
// FILE: /app/Controllers/Client/InvoiceController.php
// -------------------------------------------------------------------
// MODULE 10 — Client invoices. List (pay-now for unpaid) અને printable
// detail with GST breakup. Ownership ફરજિયાત.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class InvoiceController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $client   = db()->table('clients')->where('user_id', auth()->id())->first();
        $clientId = (int) ($client['id'] ?? 0);

        $invoices = $clientId
            ? db()->table('invoices')->where('client_id', $clientId)->orderBy('id', 'desc')->get()
            : [];

        return $this->view('client.invoices.index', ['invoices' => $invoices]);
    }

    public function show(Request $request, string $id = ''): Response
    {
        $client  = db()->table('clients')->where('user_id', auth()->id())->first();
        $invoice = db()->table('invoices')->where('id', (int) $id)->first();
        $this->authorize(
            $client !== null && $invoice !== null && (int) $invoice['client_id'] === (int) $client['id'],
            403
        );

        $items = db()->table('invoice_items')->where('invoice_id', (int) $invoice['id'])->orderBy('id', 'asc')->get();

        return $this->view('client.invoices.show', [
            'invoice' => $invoice,
            'items'   => $items,
            'client'  => $client,
        ]);
    }
}
