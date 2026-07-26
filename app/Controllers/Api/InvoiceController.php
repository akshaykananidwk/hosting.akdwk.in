<?php
// FILE: /app/Controllers/Api/InvoiceController.php
// -------------------------------------------------------------------
// 🟢 MODULE 20 — REST API: invoices. Scoped to the API key's tenant.
// JSON only.
// -------------------------------------------------------------------

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class InvoiceController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = $this->tenantId($request);
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = min(100, max(1, (int) $request->query('per_page', 20)));

        $query = db()->table('invoices');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $result = $query->orderBy('id', 'desc')->paginate($page, $perPage);

        return $this->json($result, 200);
    }

    public function show(Request $request, string $id = ''): Response
    {
        $tenantId = $this->tenantId($request);

        $query = db()->table('invoices')->where('id', (int) $id);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $invoice = $query->first();

        if (!$invoice) {
            return $this->json(['error' => 'Invoice not found'], 404);
        }

        $items = db()->table('invoice_items')
            ->where('invoice_id', (int) $invoice['id'])
            ->get();

        return $this->json(['data' => $invoice, 'items' => $items], 200);
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
