<?php
// FILE: /app/Controllers/Api/ServiceController.php
// -------------------------------------------------------------------
// 🟢 MODULE 20 — REST API: services. List/show + suspend/unsuspend via
// ProvisioningService. Scoped to the API key's tenant. JSON only.
// -------------------------------------------------------------------

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProvisioningService;

class ServiceController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = $this->tenantId($request);
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = min(100, max(1, (int) $request->query('per_page', 20)));

        $query = db()->table('services');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $result = $query->orderBy('id', 'desc')->paginate($page, $perPage);

        return $this->json($result, 200);
    }

    public function show(Request $request, string $id = ''): Response
    {
        $service = $this->find($request, (int) $id);
        if (!$service) {
            return $this->json(['error' => 'Service not found'], 404);
        }
        return $this->json(['data' => $service], 200);
    }

    public function suspend(Request $request, string $id = ''): Response
    {
        $service = $this->find($request, (int) $id);
        if (!$service) {
            return $this->json(['error' => 'Service not found'], 404);
        }

        $ok = (new ProvisioningService())->suspend((int) $service['id'], 'API suspend');
        if (!$ok) {
            return $this->json(['error' => 'Suspend failed'], 422);
        }
        return $this->json(['data' => ['id' => (int) $service['id'], 'status' => 'suspended']], 200);
    }

    public function unsuspend(Request $request, string $id = ''): Response
    {
        $service = $this->find($request, (int) $id);
        if (!$service) {
            return $this->json(['error' => 'Service not found'], 404);
        }

        $ok = (new ProvisioningService())->unsuspend((int) $service['id']);
        if (!$ok) {
            return $this->json(['error' => 'Unsuspend failed'], 422);
        }
        return $this->json(['data' => ['id' => (int) $service['id'], 'status' => 'active']], 200);
    }

    /**
     * Fetch a tenant-scoped service row (or null).
     */
    protected function find(Request $request, int $id): ?array
    {
        $tenantId = $this->tenantId($request);
        $query = db()->table('services')->where('id', $id);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        return $query->first();
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
