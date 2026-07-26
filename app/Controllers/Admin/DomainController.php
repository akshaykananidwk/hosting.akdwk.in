<?php
// FILE: /app/Controllers/Admin/DomainController.php
// -------------------------------------------------------------------
// Admin — Domains. List with client, status and expiry.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class DomainController extends Controller
{
    /** Paginated domain list joined to owning client. */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $status = trim((string) $request->query('status', ''));
        $page = max(1, (int) $request->query('page', 1));

        $query = db()->table('domains')
            ->leftJoin('clients', 'clients.id', '=', 'domains.client_id')
            ->select([
                'domains.id', 'domains.domain', 'domains.type', 'domains.status',
                'domains.registrar', 'domains.auto_renew', 'domains.reg_date',
                'domains.expiry_date',
                'clients.first_name', 'clients.last_name', 'clients.company',
            ])
            ->where('domains.tenant_id', $tenantId);

        if ($status !== '') {
            $query->where('domains.status', $status);
        }
        $result = $query->orderBy('domains.expiry_date', 'asc')->paginate($page, 20);

        return $this->view('admin.domains.index', [
            'domains' => $result['data'],
            'meta'    => $result,
            'status'  => $status,
        ]);
    }
}
