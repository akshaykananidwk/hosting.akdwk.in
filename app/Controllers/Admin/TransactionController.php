<?php
// FILE: /app/Controllers/Admin/TransactionController.php
// -------------------------------------------------------------------
// Admin — Transactions. List all gateway payments, approve
// pending manual / UPI (UTR) payments.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BillingService;

class TransactionController extends Controller
{
    /** Paginated transaction ledger. */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $status = trim((string) $request->query('status', ''));
        $page = max(1, (int) $request->query('page', 1));

        $query = db()->table('transactions')
            ->leftJoin('invoices', 'invoices.id', '=', 'transactions.invoice_id')
            ->leftJoin('clients', 'clients.id', '=', 'transactions.client_id')
            ->select([
                'transactions.id', 'transactions.gateway', 'transactions.amount',
                'transactions.status', 'transactions.utr', 'transactions.type',
                'transactions.transaction_id', 'transactions.created_at', 'transactions.invoice_id',
                'invoices.invoice_number', 'clients.first_name', 'clients.last_name',
            ])
            ->where('transactions.tenant_id', $tenantId);

        if ($status !== '') {
            $query->where('transactions.status', $status);
        }
        $result = $query->orderBy('transactions.id', 'desc')->paginate($page, 20);

        return $this->view('admin.transactions.index', [
            'transactions' => $result['data'],
            'meta'         => $result,
            'status'       => $status,
        ]);
    }

    /** Approve a pending manual / UPI payment and settle its invoice. */
    public function approve(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $txn = db()->table('transactions')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$txn) {
            return back_with('error', 'Transaction not found');
        }
        if ($txn['status'] !== 'pending') {
            return back_with('info', 'Only pending transactions can be approved');
        }
        if (!in_array($txn['gateway'], ['manual', 'upi_manual', 'bank_transfer'], true)) {
            return back_with('error', 'Manual approval is not available for this gateway');
        }

        $now = date('Y-m-d H:i:s');
        db()->table('transactions')->where('id', (int) $txn['id'])->update([
            'status'     => 'success',
            'paid_at'    => $now,
            'updated_at' => $now,
        ]);

        if (!empty($txn['invoice_id'])) {
            (new BillingService())->markPaid(
                (int) $txn['invoice_id'],
                (string) $txn['gateway'],
                $txn['utr'] ?: ($txn['transaction_id'] ?? null),
                (float) $txn['amount']
            );
        }

        audit('transaction.approve', 'Transaction', (int) $txn['id']);
        return back_with('success', 'Transaction approved ✅');
    }
}
