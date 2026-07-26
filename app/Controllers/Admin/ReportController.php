<?php
// FILE: /app/Controllers/Admin/ReportController.php
// -------------------------------------------------------------------
// Admin — Reports. Revenue (month/year), MRR, plan-wise
// sales, and a GSTR-1 style CSV export of paid invoices this month.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class ReportController extends Controller
{
    /** Revenue + MRR + plan-wise sales dashboard. */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;

        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-t 23:59:59');
        $yearStart  = date('Y-01-01 00:00:00');
        $yearEnd    = date('Y-12-31 23:59:59');

        $revenueMonth = db()->table('transactions')
            ->where('tenant_id', $tenantId)->where('status', 'success')->where('type', 'payment')
            ->where('paid_at', '>=', $monthStart)->where('paid_at', '<=', $monthEnd)->sum('amount');

        $revenueYear = db()->table('transactions')
            ->where('tenant_id', $tenantId)->where('status', 'success')->where('type', 'payment')
            ->where('paid_at', '>=', $yearStart)->where('paid_at', '<=', $yearEnd)->sum('amount');

        // Pull active services with product name for MRR + plan-wise sales.
        $services = db()->table('services')
            ->leftJoin('products', 'products.id', '=', 'services.product_id')
            ->select([
                'services.product_id', 'services.recurring_amount', 'services.status',
                'products.name as product_name',
            ])
            ->where('services.tenant_id', $tenantId)
            ->where('services.status', 'active')->get();

        $mrr = 0.0;
        $plans = [];
        foreach ($services as $s) {
            $mrr += (float) $s['recurring_amount'];
            $name = $s['product_name'] ?: 'Unassigned';
            if (!isset($plans[$name])) {
                $plans[$name] = ['name' => $name, 'count' => 0, 'revenue' => 0.0];
            }
            $plans[$name]['count']++;
            $plans[$name]['revenue'] += (float) $s['recurring_amount'];
        }
        usort($plans, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        $planMax = 0.0;
        foreach ($plans as $p) {
            $planMax = max($planMax, $p['revenue']);
        }

        $stats = [
            'clients'        => db()->table('clients')->where('tenant_id', $tenantId)->count(),
            'active_services'=> db()->table('services')->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'unpaid_invoices'=> db()->table('invoices')->where('tenant_id', $tenantId)->where('status', 'unpaid')->count(),
            'unpaid_amount'  => db()->table('invoices')->where('tenant_id', $tenantId)->whereIn('status', ['unpaid', 'overdue'])->sum('total'),
        ];

        return $this->view('admin.reports.index', [
            'revenueMonth' => $revenueMonth,
            'revenueYear'  => $revenueYear,
            'mrr'          => $mrr,
            'plans'        => $plans,
            'planMax'      => $planMax,
            'stats'        => $stats,
        ]);
    }

    /** GSTR-1 style CSV of this month's paid invoices. */
    public function gstCsv(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-t 23:59:59');

        $rows = db()->table('invoices')
            ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
            ->select([
                'invoices.invoice_number', 'invoices.issue_date', 'invoices.subtotal',
                'invoices.discount', 'invoices.cgst', 'invoices.sgst', 'invoices.igst',
                'invoices.total', 'clients.gstin',
            ])
            ->where('invoices.tenant_id', $tenantId)
            ->where('invoices.status', 'paid')
            ->where('invoices.paid_at', '>=', $monthStart)
            ->where('invoices.paid_at', '<=', $monthEnd)
            ->orderBy('invoices.id', 'asc')->get();

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['invoice_number', 'date', 'gstin', 'taxable', 'cgst', 'sgst', 'igst', 'total']);
        foreach ($rows as $r) {
            $taxable = round((float) $r['subtotal'] - (float) $r['discount'], 2);
            fputcsv($fh, [
                $r['invoice_number'],
                $r['issue_date'],
                $r['gstin'] ?? '',
                number_format($taxable, 2, '.', ''),
                number_format((float) $r['cgst'], 2, '.', ''),
                number_format((float) $r['sgst'], 2, '.', ''),
                number_format((float) $r['igst'], 2, '.', ''),
                number_format((float) $r['total'], 2, '.', ''),
            ]);
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        audit('report.gst_export');
        return Response::make($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=gst.csv');
    }
}
