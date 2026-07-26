<?php
// FILE: /app/Controllers/Admin/DashboardController.php
// -------------------------------------------------------------------
// Admin dashboard — live business + infrastructure overview tiles.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class DashboardController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $todayStart = date('Y-m-d') . ' 00:00:00';

        // Unpaid invoices — rebuild the query per aggregate (builder is not resettable).
        $unpaid = fn() => db()->table('invoices')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'overdue']);

        $data = [
            'totalClients' => db()->table('clients')->where('tenant_id', $tenantId)->count(),
            'activeServices' => db()->table('services')
                ->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'pendingProvisioning' => db()->table('provisioning_queue')
                ->where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'unpaidCount' => $unpaid()->count(),
            'unpaidSum' => $unpaid()->sum('total'),
            'todayRevenue' => db()->table('transactions')
                ->where('tenant_id', $tenantId)
                ->where('type', 'payment')
                ->where('status', 'success')
                ->where('created_at', '>=', $todayStart)
                ->sum('amount'),
            'whatsappPending' => db()->table('whatsapp_queue')->where('status', 'pending')->count(),
            'servers' => db()->table('servers')->orderBy('name', 'asc')->get(),
            'recentLogs' => db()->table('provisioning_logs')
                ->leftJoin('services', 'services.id', '=', 'provisioning_logs.service_id')
                ->select([
                    'provisioning_logs.id as id',
                    'provisioning_logs.step as step',
                    'provisioning_logs.status as status',
                    'provisioning_logs.message as message',
                    'provisioning_logs.created_at as created_at',
                    'services.domain as domain',
                ])
                ->where('provisioning_logs.tenant_id', $tenantId)
                ->orderBy('provisioning_logs.id', 'desc')
                ->limit(10)
                ->get(),
        ];

        return $this->view('admin.dashboard', $data);
    }
}
