<?php
// FILE: /app/Controllers/Reseller/DashboardController.php
// -------------------------------------------------------------------
// 🟢 MODULE 13 — RESELLER area dashboard. Resolve the reseller row for
// the logged-in user, then show clients / active services / wallet +
// recent clients. Reuses layouts/admin shell.
// -------------------------------------------------------------------

namespace App\Controllers\Reseller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class DashboardController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        // The reseller record belongs to the currently authenticated user.
        $reseller = db()->table('resellers')
            ->where('user_id', (int) auth()->id())
            ->first();

        $resellerId = (int) ($reseller['id'] ?? 0);

        $clientsCount = $resellerId
            ? db()->table('clients')->where('reseller_id', $resellerId)->count()
            : 0;

        $activeServices = $resellerId
            ? db()->table('services')
                ->where('reseller_id', $resellerId)
                ->where('status', 'active')
                ->count()
            : 0;

        $walletBalance = (float) ($reseller['wallet_balance'] ?? 0);

        $recentClients = $resellerId
            ? db()->table('clients')
                ->where('reseller_id', $resellerId)
                ->orderBy('created_at', 'desc')
                ->limit(8)
                ->get()
            : [];

        return $this->view('reseller.dashboard', [
            'reseller'       => $reseller,
            'clientsCount'   => $clientsCount,
            'activeServices' => $activeServices,
            'walletBalance'  => $walletBalance,
            'recentClients'  => $recentClients,
        ]);
    }
}
