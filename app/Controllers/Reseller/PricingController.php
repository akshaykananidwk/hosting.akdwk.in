<?php
// FILE: /app/Controllers/Reseller/PricingController.php
// -------------------------------------------------------------------
// 🟢 MODULE 13 — RESELLER pricing. Show every product with its base
// price (product_pricing) and the reseller's own override
// (reseller_pricing) + markup settings; save upserts the overrides.
// -------------------------------------------------------------------

namespace App\Controllers\Reseller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class PricingController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $reseller = db()->table('resellers')
            ->where('user_id', (int) auth()->id())
            ->first();

        $resellerId = (int) ($reseller['id'] ?? 0);
        $tenantId   = (int) ($reseller['tenant_id'] ?? auth()->tenantId());

        // Active products for this tenant.
        $products = $tenantId
            ? db()->table('products')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get()
            : [];

        // Base pricing keyed by product_id.
        $basePricing = [];
        foreach (db()->table('product_pricing')->get() as $row) {
            $basePricing[(int) $row['product_id']] = $row;
        }

        // Reseller overrides keyed by product_id.
        $overrides = [];
        if ($resellerId) {
            foreach (db()->table('reseller_pricing')->where('reseller_id', $resellerId)->get() as $row) {
                $overrides[(int) $row['product_id']] = $row;
            }
        }

        // Merge into a view-friendly structure.
        $rows = [];
        foreach ($products as $product) {
            $pid = (int) $product['id'];
            $rows[] = [
                'product'  => $product,
                'base'     => $basePricing[$pid] ?? null,
                'override' => $overrides[$pid] ?? null,
            ];
        }

        return $this->view('reseller.pricing', [
            'reseller' => $reseller,
            'rows'     => $rows,
        ]);
    }

    public function save(Request $request, string $id = ''): Response
    {
        $reseller = db()->table('resellers')
            ->where('user_id', (int) auth()->id())
            ->first();

        if (!$reseller) {
            return back_with('error', 'Reseller profile not found.');
        }

        $resellerId = (int) $reseller['id'];
        $tenantId   = (int) ($reseller['tenant_id'] ?? auth()->tenantId());

        // Valid product ids for this tenant — never write cross-tenant rows.
        $validIds = [];
        foreach (db()->table('products')->where('tenant_id', $tenantId)->get() as $p) {
            $validIds[(int) $p['id']] = true;
        }

        $monthly    = (array) $request->input('monthly', []);
        $quarterly  = (array) $request->input('quarterly', []);
        $halfYearly = (array) $request->input('half_yearly', []);
        $yearly     = (array) $request->input('yearly', []);

        // Every product id referenced by any cycle input.
        $pids = array_map('intval', array_unique(array_merge(
            array_keys($monthly),
            array_keys($quarterly),
            array_keys($halfYearly),
            array_keys($yearly),
        )));

        $saved = 0;
        foreach ($pids as $pid) {
            if ($pid <= 0 || !isset($validIds[$pid])) {
                continue;
            }

            $values = [
                'monthly'     => $this->price($monthly[$pid] ?? null),
                'quarterly'   => $this->price($quarterly[$pid] ?? null),
                'half_yearly' => $this->price($halfYearly[$pid] ?? null),
                'yearly'      => $this->price($yearly[$pid] ?? null),
            ];

            $exists = db()->table('reseller_pricing')
                ->where('reseller_id', $resellerId)
                ->where('product_id', $pid)
                ->first();

            if ($exists) {
                db()->table('reseller_pricing')
                    ->where('id', (int) $exists['id'])
                    ->update(array_merge($values, ['updated_at' => date('Y-m-d H:i:s')]));
            } else {
                db()->table('reseller_pricing')->insert(array_merge($values, [
                    'tenant_id'   => $tenantId,
                    'reseller_id' => $resellerId,
                    'product_id'  => $pid,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]));
            }
            $saved++;
        }

        // Optionally update the reseller's markup settings.
        if ($request->has('markup_type') || $request->has('markup_value')) {
            $markupType = $request->input('markup_type') === 'fixed' ? 'fixed' : 'percent';
            db()->table('resellers')->where('id', $resellerId)->update([
                'markup_type'  => $markupType,
                'markup_value' => (float) $request->input('markup_value', 0),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return back_with('success', $saved . ' product price(s) saved.');
    }

    /**
     * Normalize a submitted price: blank → null, otherwise a float.
     */
    protected function price(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }
}
