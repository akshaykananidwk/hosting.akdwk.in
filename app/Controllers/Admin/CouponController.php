<?php
// FILE: /app/Controllers/Admin/CouponController.php
// -------------------------------------------------------------------
// Admin — Coupons (કૂપન). List + inline create.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class CouponController extends Controller
{
    /** Coupon list + inline create form. */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $coupons = db()->table('coupons')
            ->where('tenant_id', $tenantId)->orderBy('id', 'desc')->get();

        return $this->view('admin.coupons.index', [
            'coupons' => $coupons,
        ]);
    }

    /** Persist a new coupon. */
    public function store(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $data = $this->validate($request, [
            'code'  => 'required|max:60|unique:coupons,code',
            'type'  => 'required|in:percent,fixed',
            'value' => 'required|numeric',
        ]);
        $now = date('Y-m-d H:i:s');

        $couponId = db()->table('coupons')->insert([
            'tenant_id'  => $tenantId,
            'code'       => strtoupper(trim((string) $data['code'])),
            'type'       => $data['type'],
            'value'      => round((float) $data['value'], 2),
            'applies_to' => in_array($request->input('applies_to'), ['all', 'product', 'group'], true)
                ? (string) $request->input('applies_to') : 'all',
            'max_uses'   => $request->filled('max_uses') ? (int) $request->input('max_uses') : null,
            'per_client' => (int) $request->input('per_client', 1),
            'min_amount' => round((float) $request->input('min_amount', 0), 2),
            'starts_at'  => $request->filled('starts_at') ? (string) $request->input('starts_at') : null,
            'expires_at' => $request->filled('expires_at') ? (string) $request->input('expires_at') : null,
            'status'     => in_array($request->input('status'), ['active', 'disabled'], true)
                ? (string) $request->input('status') : 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        audit('coupon.create', 'Coupon', (int) $couponId, ['code' => $data['code']]);
        return redirect_route('admin/coupons', 'success', 'કૂપન બની ગઈ ✅');
    }
}
