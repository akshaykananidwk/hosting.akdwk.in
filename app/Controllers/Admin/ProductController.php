<?php
// FILE: /app/Controllers/Admin/ProductController.php
// -------------------------------------------------------------------
// Admin — Products / Plans. Grouped listing, create + edit
// with per-cycle pricing (product_pricing).
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class ProductController extends Controller
{
    /** Products grouped by product_groups, each with its pricing row. */
    public function index(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;

        $groups = db()->table('product_groups')
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();

        $products = db()->table('products')
            ->leftJoin('product_pricing', 'product_pricing.product_id', '=', 'products.id')
            ->select([
                'products.id', 'products.group_id', 'products.name', 'products.status',
                'products.disk_mb', 'products.bandwidth_mb', 'products.is_bw_unlimited',
                'products.max_sites', 'products.max_databases', 'products.php_version',
                'products.free_ssl', 'products.backup_freq',
                'product_pricing.monthly', 'product_pricing.quarterly',
                'product_pricing.half_yearly', 'product_pricing.yearly',
            ])
            ->where('products.tenant_id', $tenantId)
            ->orderBy('products.sort_order', 'asc')->orderBy('products.id', 'asc')->get();

        // Bucket products under their group id.
        $byGroup = [];
        foreach ($products as $p) {
            $byGroup[(int) $p['group_id']][] = $p;
        }

        return $this->view('admin.products.index', [
            'groups'  => $groups,
            'byGroup' => $byGroup,
        ]);
    }

    /** New-product form. */
    public function create(Request $request, string $id = ''): Response
    {
        return $this->view('admin.products.form', [
            'product' => $this->emptyProduct(),
            'pricing' => $this->emptyPricing(),
            'groups'  => $this->groups(),
            'action'  => url('admin/products'),
            'isEdit'  => false,
        ]);
    }

    /** Persist a new product + pricing. */
    public function store(Request $request, string $id = ''): Response
    {
        $data = $this->validate($request, [
            'name'     => 'required|max:150',
            'group_id' => 'required|integer',
        ]);
        $tenantId = auth()->tenantId() ?? 1;
        $now = date('Y-m-d H:i:s');

        $group = db()->table('product_groups')
            ->where('id', (int) $data['group_id'])->where('tenant_id', $tenantId)->first();
        $type = $group['type'] ?? 'shared';

        $productId = db()->table('products')->insert(
            $this->productFields($request, $data['name'], (int) $data['group_id'], $type, $tenantId, $now, true)
        );

        db()->table('product_pricing')->insert(array_merge(
            $this->pricingFields($request),
            [
                'tenant_id'  => $tenantId,
                'product_id' => $productId,
                'currency'   => 'INR',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ));

        audit('product.create', 'Product', (int) $productId, ['name' => $data['name']]);
        return redirect_route('admin/products', 'success', 'Product created ✅');
    }

    /** Edit form with existing product + pricing. */
    public function edit(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $product = db()->table('products')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$product) {
            abort(404, 'Product not found');
        }
        $pricing = db()->table('product_pricing')
            ->where('product_id', (int) $product['id'])->first() ?? $this->emptyPricing();

        return $this->view('admin.products.form', [
            'product' => $product,
            'pricing' => $pricing,
            'groups'  => $this->groups(),
            'action'  => url('admin/products/' . $product['id']),
            'isEdit'  => true,
        ]);
    }

    /** Update product + pricing. */
    public function update(Request $request, string $id = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;
        $product = db()->table('products')
            ->where('id', (int) $id)->where('tenant_id', $tenantId)->first();
        if (!$product) {
            abort(404, 'Product not found');
        }
        $data = $this->validate($request, [
            'name'     => 'required|max:150',
            'group_id' => 'required|integer',
        ]);
        $now = date('Y-m-d H:i:s');

        $group = db()->table('product_groups')
            ->where('id', (int) $data['group_id'])->where('tenant_id', $tenantId)->first();
        $type = $group['type'] ?? ($product['type'] ?? 'shared');

        $fields = $this->productFields($request, $data['name'], (int) $data['group_id'], $type, $tenantId, $now, false);
        db()->table('products')->where('id', (int) $product['id'])->update($fields);

        $existing = db()->table('product_pricing')->where('product_id', (int) $product['id'])->first();
        $priceFields = array_merge($this->pricingFields($request), ['updated_at' => $now]);
        if ($existing) {
            db()->table('product_pricing')->where('id', (int) $existing['id'])->update($priceFields);
        } else {
            db()->table('product_pricing')->insert(array_merge($priceFields, [
                'tenant_id'  => $tenantId,
                'product_id' => (int) $product['id'],
                'currency'   => 'INR',
                'created_at' => $now,
            ]));
        }

        audit('product.update', 'Product', (int) $product['id']);
        return redirect_route('admin/products', 'success', 'Product updated ✅');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function groups(): array
    {
        $tenantId = auth()->tenantId() ?? 1;
        return db()->table('product_groups')
            ->where('tenant_id', $tenantId)->orderBy('sort_order', 'asc')->get();
    }

    /** Build the products row from request input. */
    private function productFields(Request $request, string $name, int $groupId, string $type, int $tenantId, string $now, bool $isNew): array
    {
        $bwUnlimited = $request->boolean('is_bw_unlimited') ? 1 : 0;
        $fields = [
            'group_id'        => $groupId,
            'name'            => $name,
            'slug'            => slugify($name) ?: ('plan-' . str_random(5)),
            'description'     => trim((string) $request->input('description', '')) ?: null,
            'type'            => $type,
            'disk_mb'         => (int) $request->input('disk_mb', 1024),
            'bandwidth_mb'    => (int) $request->input('bandwidth_mb', 20480),
            'is_bw_unlimited' => $bwUnlimited,
            'max_sites'       => (int) $request->input('max_sites', 1),
            'max_databases'   => (int) $request->input('max_databases', 1),
            'max_ftp'         => (int) $request->input('max_ftp', 1),
            'php_version'     => (string) $request->input('php_version', '82'),
            'free_ssl'        => $request->boolean('free_ssl') ? 1 : 0,
            'backup_freq'     => in_array($request->input('backup_freq'), ['none', 'daily', 'weekly', 'monthly'], true)
                ? (string) $request->input('backup_freq') : 'daily',
            'setup_fee'       => round((float) $request->input('setup_fee', 0), 2),
            'status'          => in_array($request->input('status'), ['active', 'hidden', 'retired'], true)
                ? (string) $request->input('status') : 'active',
            'updated_at'      => $now,
        ];
        if ($isNew) {
            $fields['tenant_id'] = $tenantId;
            $fields['created_at'] = $now;
        }
        return $fields;
    }

    /** Build the product_pricing row from request input (nullable prices). */
    private function pricingFields(Request $request): array
    {
        $price = function (string $key) use ($request) {
            $v = $request->input($key);
            return ($v === null || $v === '') ? null : round((float) $v, 2);
        };
        return [
            'monthly'     => $price('monthly'),
            'quarterly'   => $price('quarterly'),
            'half_yearly' => $price('half_yearly'),
            'yearly'      => $price('yearly'),
        ];
    }

    private function emptyProduct(): array
    {
        return [
            'id' => 0, 'group_id' => 0, 'name' => '', 'description' => '',
            'disk_mb' => 1024, 'bandwidth_mb' => 20480, 'is_bw_unlimited' => 0,
            'max_sites' => 1, 'max_databases' => 1, 'max_ftp' => 1,
            'php_version' => '82', 'free_ssl' => 1, 'backup_freq' => 'daily',
            'setup_fee' => 0, 'status' => 'active',
        ];
    }

    private function emptyPricing(): array
    {
        return ['monthly' => '', 'quarterly' => '', 'half_yearly' => '', 'yearly' => ''];
    }
}
