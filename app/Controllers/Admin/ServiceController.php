<?php
// FILE: /app/Controllers/Admin/ServiceController.php
// -------------------------------------------------------------------
// Admin — hosting services list, detail + lifecycle (suspend/terminate).
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProvisioningService;

class ServiceController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $page = max(1, (int) $request->query('page', 1));

        $result = db()->table('services')
            ->leftJoin('clients', 'clients.id', '=', 'services.client_id')
            ->select([
                'services.id as id',
                'services.domain as domain',
                'services.status as status',
                'services.next_due_date as next_due_date',
                'services.disk_used_mb as disk_used_mb',
                'services.disk_limit_mb as disk_limit_mb',
                'services.bandwidth_used_mb as bandwidth_used_mb',
                'services.bandwidth_limit_mb as bandwidth_limit_mb',
                'services.is_bw_unlimited as is_bw_unlimited',
                'clients.first_name as first_name',
                'clients.last_name as last_name',
                'clients.company as company',
            ])
            ->where('services.tenant_id', $tenantId)
            ->orderBy('services.id', 'desc')
            ->paginate($page, 20);

        return $this->view('admin.services.index', ['page' => $result]);
    }

    public function show(Request $request, string $id = ''): Response
    {
        $sid = (int) $id;
        $service = db()->table('services')->where('id', $sid)->first();
        if (!$service) {
            $this->authorize(false, 404, 'Service not found.');
        }

        $client = db()->table('clients')->where('id', (int) $service['client_id'])->first();
        $server = $service['server_id']
            ? db()->table('servers')->where('id', (int) $service['server_id'])->first()
            : null;
        $details = db()->table('service_details')->where('service_id', $sid)->first();

        $dbPass = ($details && !empty($details['db_pass_enc'])) ? decrypt($details['db_pass_enc']) : null;
        $ftpPass = ($details && !empty($details['ftp_pass_enc'])) ? decrypt($details['ftp_pass_enc']) : null;

        $logs = db()->table('provisioning_logs')
            ->where('service_id', $sid)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $disk = db()->table('disk_usage')
            ->where('service_id', $sid)
            ->orderBy('checked_at', 'desc')
            ->first();

        $bwMonth = db()->table('bandwidth_usage')
            ->where('service_id', $sid)
            ->where('usage_date', '>=', date('Y-m-01'))
            ->sum('bytes');

        return $this->view('admin.services.show', [
            'service' => $service,
            'client' => $client,
            'server' => $server,
            'details' => $details,
            'dbPass' => $dbPass,
            'ftpPass' => $ftpPass,
            'logs' => $logs,
            'disk' => $disk,
            'bwMonth' => $bwMonth,
        ]);
    }

    public function suspend(Request $request, string $id = ''): Response
    {
        $sid = (int) $id;
        $reason = trim((string) $request->input('reason', '')) ?: 'Admin suspend';
        $ok = (new ProvisioningService())->suspend($sid, $reason);
        audit('service.suspend', 'service', $sid, ['reason' => $reason]);
        return $ok
            ? back_with('success', 'Service suspended.')
            : back_with('error', 'Suspend failed.');
    }

    public function unsuspend(Request $request, string $id = ''): Response
    {
        $sid = (int) $id;
        $ok = (new ProvisioningService())->unsuspend($sid);
        audit('service.unsuspend', 'service', $sid);
        return $ok
            ? back_with('success', 'Service reactivated.')
            : back_with('error', 'Unsuspend failed.');
    }

    public function terminate(Request $request, string $id = ''): Response
    {
        $sid = (int) $id;
        $ok = (new ProvisioningService())->terminate($sid);
        audit('service.terminate', 'service', $sid);
        return $ok
            ? back_with('success', 'Service terminated.')
            : back_with('error', 'Terminate failed.');
    }
}
