<?php
// FILE: /app/Controllers/Api/ClientController.php
// -------------------------------------------------------------------
// 🟢 MODULE 20 — REST API: clients. Scoped to the API key's tenant.
// JSON only. Auth handled by ApiAuthMiddleware ('api' alias).
// -------------------------------------------------------------------

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class ClientController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = $this->tenantId($request);
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = min(100, max(1, (int) $request->query('per_page', 20)));

        $query = db()->table('clients');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $result = $query->orderBy('id', 'desc')->paginate($page, $perPage);

        return $this->json($result, 200);
    }

    public function show(Request $request, string $id = ''): Response
    {
        $tenantId = $this->tenantId($request);

        $query = db()->table('clients')->where('id', (int) $id);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $client = $query->first();

        if (!$client) {
            return $this->json(['error' => 'Client not found'], 404);
        }
        return $this->json(['data' => $client], 200);
    }

    /**
     * Tenant of the authenticated API key (null → unscoped, e.g. super admin).
     */
    protected function tenantId(Request $request): ?int
    {
        $tenant = auth()->tenantId();
        if ($tenant !== null) {
            return $tenant;
        }
        $key = $request->attribute('api_key');
        return isset($key['tenant_id']) && $key['tenant_id'] !== null ? (int) $key['tenant_id'] : null;
    }
}
