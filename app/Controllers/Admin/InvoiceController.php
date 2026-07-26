<?php
// FILE: /app/Controllers/Admin/InvoiceController.php
// -------------------------------------------------------------------
// Admin — Invoices (ઇન્વોઇસ). List + status filter, printable GST
// invoice, mark-paid via BillingService.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BillingService;

class InvoiceController extends Controller
{
    /** Paginated invoice list with ?status= filter + client name. */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $status = trim((string) $request->query('status', ''));
        $page = max(1, (int) $request->query('page', 1));

        $query = db()->table('invoices')
            ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
            ->select([
                'invoices.id', 'invoices.invoice_number', 'invoices.status',
                'invoices.total', 'invoices.due_date', 'invoices.issue_date',
                'clients.first_name', 'clients.last_name', 'clients.company',
            ])
            ->where('invoices.tenant_id', $tenantId);

        if ($status !== '') {
            $query->where('invoices.status', $status);
        }
        $result = $query->orderBy('invoices.id', 'desc')->paginate($page, 20);

        return $this->view('admin.invoices.index', [
            'invoices' => $result['data'],
            'meta'     => $result,
            'status'   => $status,
        ]);
    }

    /** Printable invoice with GST breakup + client + transactions. */
    public function show(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $invoice = db()->table('invoices')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$invoice) {
            abort(404, 'ઇન્વોઇસ મળ્યું નહીં');
        }

        $client = db()->table('clients')->where('id', (int) $invoice['client_id'])->first();
        $items = db()->table('invoice_items')
            ->where('invoice_id', (int) $invoice['id'])->orderBy('id', 'asc')->get();
        $transactions = db()->table('transactions')
            ->where('invoice_id', (int) $invoice['id'])->orderBy('id', 'desc')->get();

        return $this->view('admin.invoices.show', [
            'invoice'      => $invoice,
            'client'       => $client ?? [],
            'items'        => $items,
            'transactions' => $transactions,
        ]);
    }

    /** Mark an invoice paid manually. */
    public function markPaid(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $invoice = db()->table('invoices')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$invoice) {
            return back_with('error', 'ઇન્વોઇસ મળ્યું નહીં');
        }
        if ($invoice['status'] === 'paid') {
            return back_with('info', 'આ ઇન્વોઇસ પહેલેથી paid છે');
        }

        $ok = (new BillingService())->markPaid((int) $id, 'manual');
        if ($ok) {
            audit('invoice.mark_paid', 'Invoice', (int) $id);
            return back_with('success', 'ઇન્વોઇસ paid તરીકે માર્ક થયું ✅');
        }
        return back_with('error', 'ઇન્વોઇસ paid કરી શકાયું નહીં');
    }
}
