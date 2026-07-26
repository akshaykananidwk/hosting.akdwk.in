<?php
// FILE: /app/Controllers/Admin/ProvisioningController.php
// -------------------------------------------------------------------
// Admin — provisioning queue monitor + retry failed/stuck jobs.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class ProvisioningController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;

        $queue = db()->table('provisioning_queue')
            ->leftJoin('services', 'services.id', '=', 'provisioning_queue.service_id')
            ->select([
                'provisioning_queue.id as id',
                'provisioning_queue.action as action',
                'provisioning_queue.status as status',
                'provisioning_queue.attempts as attempts',
                'provisioning_queue.max_attempts as max_attempts',
                'provisioning_queue.last_error as last_error',
                'provisioning_queue.created_at as created_at',
                'services.domain as domain',
            ])
            ->where('provisioning_queue.tenant_id', $tenantId)
            ->orderBy('provisioning_queue.id', 'desc')
            ->limit(100)
            ->get();

        $logs = db()->table('provisioning_logs')
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
            ->limit(50)
            ->get();

        return $this->view('admin.provisioning.index', ['queue' => $queue, 'logs' => $logs]);
    }

    public function retry(Request $request, string $id = ''): Response
    {
        $qid = (int) $id;
        $affected = db()->table('provisioning_queue')->where('id', $qid)->update([
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'available_at' => now(),
            'reserved_at' => null,
            'updated_at' => now(),
        ]);
        audit('provisioning.retry', 'provisioning_queue', $qid);
        return $affected
            ? back_with('success', 'Job re-queued.')
            : back_with('error', 'Job not found.');
    }
}
