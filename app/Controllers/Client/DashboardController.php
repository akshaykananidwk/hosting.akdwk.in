<?php
// FILE: /app/Controllers/Client/DashboardController.php
// -------------------------------------------------------------------
// MODULE 10 — Client area dashboard. સ્વાગત, stat tiles, services
// usage bars, recent invoices અને announcements બતાવે.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class DashboardController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $client   = db()->table('clients')->where('user_id', auth()->id())->first();
        $clientId = (int) ($client['id'] ?? 0);
        $tenantId = (int) ($client['tenant_id'] ?? (auth()->tenantId() ?? 1));

        // Client's services (active/pending/suspended shown on dashboard).
        $services = $clientId
            ? db()->table('services')
                ->where('client_id', $clientId)
                ->whereIn('status', ['active', 'pending', 'suspended'])
                ->orderBy('id', 'desc')
                ->get()
            : [];

        $activeCount = 0;
        foreach ($services as $s) {
            if (($s['status'] ?? '') === 'active') {
                $activeCount++;
            }
        }

        $unpaidCount = $clientId
            ? db()->table('invoices')->where('client_id', $clientId)->whereIn('status', ['unpaid', 'overdue'])->count()
            : 0;
        $unpaidSum = $clientId
            ? db()->table('invoices')->where('client_id', $clientId)->whereIn('status', ['unpaid', 'overdue'])->sum('total')
            : 0.0;
        $openTickets = $clientId
            ? db()->table('tickets')->where('client_id', $clientId)->whereIn('status', ['open', 'answered', 'customer_reply', 'on_hold'])->count()
            : 0;

        $recentInvoices = $clientId
            ? db()->table('invoices')->where('client_id', $clientId)->orderBy('id', 'desc')->limit(5)->get()
            : [];

        $announcements = db()->table('announcements')
            ->where('tenant_id', $tenantId)
            ->where('is_published', 1)
            ->whereIn('audience', ['all', 'clients'])
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return $this->view('client.dashboard', [
            'client'         => $client,
            'services'       => $services,
            'activeCount'    => $activeCount,
            'unpaidCount'    => $unpaidCount,
            'unpaidSum'      => $unpaidSum,
            'openTickets'    => $openTickets,
            'recentInvoices' => $recentInvoices,
            'announcements'  => $announcements,
        ]);
    }
}
