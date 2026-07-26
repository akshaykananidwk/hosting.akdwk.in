<?php
// FILE: /app/Controllers/OrderController.php
// -------------------------------------------------------------------
// MODULES 6/7/8 STOREFRONT — Order + Provisioning frontend.
// Catalog → Configure → Place order → Checkout → Pay → Success.
// Razorpay + Manual UPI gateways. Paid invoice → auto provision
// (BillingService::markPaid → ProvisioningService::enqueue).
// -------------------------------------------------------------------

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BillingService;
use App\Services\PaymentService;

class OrderController extends Controller
{
    /** Billing cycles offered in the storefront (maps to product_pricing columns). */
    protected const CYCLES = [
        'monthly'     => 'માસિક (Monthly)',
        'quarterly'   => 'ત્રિમાસિક (Quarterly)',
        'half_yearly' => 'અર્ધવાર્ષિક (Half-Yearly)',
        'yearly'      => 'વાર્ષિક (Yearly)',
    ];

    // -----------------------------------------------------------------
    // Catalog — public product listing (grouped)
    // -----------------------------------------------------------------

    public function catalog(Request $request, string $param = ''): Response
    {
        $tenantId = auth()->tenantId() ?? 1;

        $groups = db()->table('product_groups')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($groups as &$group) {
            $products = db()->table('products')
                ->where('group_id', (int) $group['id'])
                ->where('status', 'active')
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($products as &$product) {
                $pricing = db()->table('product_pricing')
                    ->where('product_id', (int) $product['id'])
                    ->first();
                $product['pricing'] = $pricing;
                $product['monthly'] = $pricing && $pricing['monthly'] !== null
                    ? (float) $pricing['monthly'] : null;
            }
            unset($product);
            $group['products'] = $products;
        }
        unset($group);

        return $this->view('store.catalog', ['groups' => $groups]);
    }

    // -----------------------------------------------------------------
    // Configure — pick domain + billing cycle for a product
    // -----------------------------------------------------------------

    public function configure(Request $request, string $slug = ''): Response
    {
        $product = db()->table('products')
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (!$product) {
            $this->authorize(false, 404, 'પ્રોડક્ટ મળી નથી (Not Found).');
        }

        $pricing = db()->table('product_pricing')
            ->where('product_id', (int) $product['id'])
            ->first();

        $cycles = [];
        foreach (self::CYCLES as $key => $label) {
            if ($pricing && $pricing[$key] !== null && (float) $pricing[$key] > 0) {
                $cycles[] = ['key' => $key, 'label' => $label, 'price' => (float) $pricing[$key]];
            }
        }

        return $this->view('store.configure', [
            'product' => $product,
            'pricing' => $pricing,
            'cycles'  => $cycles,
        ]);
    }

    // -----------------------------------------------------------------
    // Place — create order + service + invoice (login required)
    // -----------------------------------------------------------------

    public function place(Request $request, string $param = ''): Response
    {
        if (auth()->guest()) {
            return redirect_route('login', 'error', 'ઓર્ડર કરવા માટે પહેલા લોગિન કરો.');
        }

        $productId  = (int) $request->input('product_id');
        $domain     = strtolower(trim((string) $request->input('domain')));
        $cycle      = (string) $request->input('billing_cycle');
        $couponCode = trim((string) $request->input('coupon', ''));

        // ---- Validate ----
        if ($productId <= 0 || $domain === '' || !array_key_exists($cycle, self::CYCLES)) {
            return back_with('error', 'બધી વિગતો ભરો — પ્રોડક્ટ, ડોમેન અને બિલિંગ સાયકલ જરૂરી છે.');
        }
        if (!preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.[a-z0-9-]{1,63})*\.[a-z]{2,}$/', $domain)) {
            return back_with('error', 'માન્ય ડોમેન નાખો (દા.ત. example.com).');
        }

        $product = db()->table('products')
            ->where('id', $productId)
            ->where('status', 'active')
            ->first();
        if (!$product) {
            return back_with('error', 'પ્રોડક્ટ મળી નથી.');
        }

        $pricing = db()->table('product_pricing')->where('product_id', $productId)->first();
        $price = $pricing && $pricing[$cycle] !== null ? (float) $pricing[$cycle] : 0.0;
        if ($price <= 0) {
            return back_with('error', 'આ બિલિંગ સાયકલ માટે કિંમત ઉપલબ્ધ નથી.');
        }

        // ---- Resolve (or create) the current client ----
        $tenantId = (int) ($product['tenant_id'] ?? (auth()->tenantId() ?? 1));
        $client = $this->currentClient($tenantId);
        $tenantId = (int) $client['tenant_id'];

        // ---- Coupon (optional) ----
        $discount = 0.0;
        $couponId = null;
        $appliedCoupon = null;
        if ($couponCode !== '') {
            $c = db()->table('coupons')
                ->where('tenant_id', $tenantId)
                ->where('code', $couponCode)
                ->where('status', 'active')
                ->first();
            if ($c) {
                $today = date('Y-m-d');
                $okDate = (empty($c['starts_at']) || $c['starts_at'] <= $today)
                    && (empty($c['expires_at']) || $c['expires_at'] >= $today);
                $okUses = $c['max_uses'] === null || (int) $c['used_count'] < (int) $c['max_uses'];
                $okMin  = $price >= (float) $c['min_amount'];
                $okApplies = $c['applies_to'] === 'all'
                    || ($c['applies_to'] === 'product' && (int) $c['applies_id'] === $productId)
                    || ($c['applies_to'] === 'group' && (int) $c['applies_id'] === (int) $product['group_id']);
                if ($okDate && $okUses && $okMin && $okApplies) {
                    $discount = $c['type'] === 'percent'
                        ? round($price * (float) $c['value'] / 100, 2)
                        : (float) $c['value'];
                    $discount = min($discount, $price);
                    $couponId = (int) $c['id'];
                    $appliedCoupon = $c;
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        $billing = new BillingService();
        $nextDue = $billing->addCycle(date('Y-m-d'), $cycle);
        $lineDesc = ($product['name'] ?? 'Hosting') . ' - ' . $domain;

        // ---- Order ----
        $orderId = db()->table('orders')->insert([
            'tenant_id'      => $tenantId,
            'client_id'      => (int) $client['id'],
            'order_number'   => $this->nextOrderNumber(),
            'status'         => 'pending',
            'subtotal'       => $price,
            'discount'       => $discount,
            'tax'            => 0,
            'total'          => max(0, $price - $discount),
            'currency'       => 'INR',
            'coupon_id'      => $couponId,
            'payment_method' => null,
            'ip_address'     => $request->ip(),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        // ---- Order item ----
        db()->table('order_items')->insert([
            'tenant_id'     => $tenantId,
            'order_id'      => $orderId,
            'product_id'    => $productId,
            'type'          => 'hosting',
            'description'   => $lineDesc,
            'domain'        => $domain,
            'billing_cycle' => $cycle,
            'qty'           => 1,
            'unit_price'    => $price,
            'setup_fee'     => (float) ($product['setup_fee'] ?? 0),
            'total'         => $price,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // ---- Service (pending until paid → provisioned) ----
        $serviceId = db()->table('services')->insert([
            'tenant_id'          => $tenantId,
            'client_id'          => (int) $client['id'],
            'order_id'           => $orderId,
            'product_id'         => $productId,
            'domain'             => $domain,
            'billing_cycle'      => $cycle,
            'first_payment'      => $price,
            'recurring_amount'   => $price,
            'status'             => 'pending',
            'disk_limit_mb'      => (int) ($product['disk_mb'] ?? 1024),
            'bandwidth_limit_mb' => (int) ($product['bandwidth_mb'] ?? 20480),
            'is_bw_unlimited'    => (int) ($product['is_bw_unlimited'] ?? 0),
            'php_version'        => (string) ($product['php_version'] ?? '82'),
            'next_due_date'      => $nextDue,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        // ---- Invoice (GST computed inside BillingService) ----
        $invoiceId = $billing->createInvoice(
            $client,
            [[
                'description'  => $lineDesc,
                'service_id'   => $serviceId,
                'amount'       => $price,
                'period_start' => date('Y-m-d'),
                'period_end'   => $nextDue,
            ]],
            ['due_days' => 0, 'discount' => $discount]
        );

        // ---- Record coupon usage (best-effort) ----
        if ($couponId && $appliedCoupon) {
            db()->table('coupon_usage')->insert([
                'tenant_id'  => $tenantId,
                'coupon_id'  => $couponId,
                'client_id'  => (int) $client['id'],
                'order_id'   => $orderId,
                'discount'   => $discount,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            db()->table('coupons')->where('id', $couponId)->update([
                'used_count' => (int) $appliedCoupon['used_count'] + 1,
                'updated_at' => $now,
            ]);
        }

        audit('order.placed', 'order', $orderId, ['invoice_id' => $invoiceId, 'domain' => $domain]);

        return $this->redirect(url('order/checkout/' . $invoiceId));
    }

    // -----------------------------------------------------------------
    // Checkout — show invoice + payment options
    // -----------------------------------------------------------------

    public function checkout(Request $request, string $invoice = ''): Response
    {
        [$inv, $client] = $this->resolveInvoice((int) $invoice);

        $items = db()->table('invoice_items')
            ->where('invoice_id', (int) $inv['id'])
            ->get();

        $payment  = new PaymentService();
        $gateways = $payment->activeGateways();

        // Pre-compute manual UPI display fields if that gateway is active.
        $upi = null;
        foreach ($gateways as $g) {
            if (($g['driver'] ?? '') === 'upi_manual') {
                $driver = $payment->driver('upi_manual');
                if ($driver) {
                    $res = $driver->initiate($inv, $client);
                    $upi = $res['fields'] ?? null;
                }
            }
        }

        return $this->view('store.checkout', [
            'invoice'  => $inv,
            'client'   => $client,
            'items'    => $items,
            'gateways' => $gateways,
            'upi'      => $upi,
        ]);
    }

    // -----------------------------------------------------------------
    // Pay — initiate the chosen gateway
    // -----------------------------------------------------------------

    public function pay(Request $request, string $invoice = ''): Response
    {
        [$inv, $client] = $this->resolveInvoice((int) $invoice);

        if (($inv['status'] ?? '') === 'paid') {
            return $this->redirect(url('order/success/' . $inv['id']));
        }

        $gateway = (string) $request->input('gateway', '');

        // ---- Manual UPI: record a pending transaction for admin approval ----
        if ($gateway === 'upi_manual') {
            $utr = trim((string) $request->input('utr', ''));
            $now = date('Y-m-d H:i:s');
            db()->table('transactions')->insert([
                'tenant_id'      => (int) $inv['tenant_id'],
                'invoice_id'     => (int) $inv['id'],
                'client_id'      => (int) $inv['client_id'],
                'gateway'        => 'upi_manual',
                'transaction_id' => $utr !== '' ? $utr : null,
                'type'           => 'payment',
                'amount'         => (float) $inv['total'],
                'currency'       => 'INR',
                'status'         => 'pending',
                'utr'            => $utr !== '' ? $utr : null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            audit('payment.upi_manual', 'invoice', (int) $inv['id'], ['utr' => $utr]);
            return back_with('success', 'ચુકવણી verify થવા મોકલી — admin approve કરશે.');
        }

        // ---- Razorpay: create an order + open hosted checkout ----
        if ($gateway === 'razorpay') {
            $driver = (new PaymentService())->driver('razorpay');
            if (!$driver) {
                return back_with('error', 'Razorpay gateway સક્રિય નથી — admin ને keys configure કરવા કહો.');
            }
            $res = $driver->initiate($inv, $client);
            $fields = $res['fields'] ?? [];
            if (empty($res['order_ref']) || empty($fields['order_id'])) {
                return back_with('error', 'Razorpay keys configure કરો (order બની શક્યો નહીં).');
            }
            return $this->razorpayCheckout($fields, (int) $inv['id']);
        }

        return back_with('error', 'ચૂકવણી પદ્ધતિ પસંદ કરો.');
    }

    // -----------------------------------------------------------------
    // Success — thank-you page
    // -----------------------------------------------------------------

    public function success(Request $request, string $invoice = ''): Response
    {
        [$inv] = $this->resolveInvoice((int) $invoice);
        return $this->view('store.success', ['invoice' => $inv]);
    }

    // -----------------------------------------------------------------
    // Razorpay webhook — signature-verified, marks invoice paid.
    // (Route has NO csrf/auth middleware.)
    // -----------------------------------------------------------------

    public function razorpayWebhook(Request $request, string $param = ''): Response
    {
        $raw = file_get_contents('php://input') ?: '';
        $sig = (string) $request->header('X-Razorpay-Signature', '');

        $driver = (new PaymentService())->driver('razorpay');
        if (!$driver) {
            return $this->json(['ok' => false, 'error' => 'gateway_inactive']);
        }

        $payload = $request->all() + ['_raw_body' => $raw, '_raw_signature' => $sig];
        $ref = $driver->verify($payload, ['X-Razorpay-Signature' => $sig]);
        if (!$ref) {
            return $this->json(['ok' => false, 'error' => 'invalid_signature'], 400);
        }

        // Resolve the invoice from the webhook body (notes.invoice_id or order receipt).
        $invoiceId = 0;
        $body = json_decode($raw, true);
        if (is_array($body)) {
            $entity = $body['payload']['payment']['entity'] ?? [];
            $invoiceId = (int) ($entity['notes']['invoice_id'] ?? 0);
            if ($invoiceId <= 0) {
                $receipt = $body['payload']['order']['entity']['receipt']
                    ?? ($entity['notes']['invoice_number'] ?? '');
                if ($receipt !== '') {
                    $row = db()->table('invoices')->where('invoice_number', (string) $receipt)->first();
                    $invoiceId = $row ? (int) $row['id'] : 0;
                }
            }
        }

        if ($invoiceId > 0) {
            (new BillingService())->markPaid($invoiceId, 'razorpay', is_string($ref) ? $ref : null);
        }

        return $this->json(['ok' => true]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Load an invoice and verify it belongs to the current logged-in
     * client. Returns [invoice, client]; aborts 403 otherwise.
     *
     * @return array{0:array,1:array}
     */
    protected function resolveInvoice(int $invoiceId): array
    {
        $client = db()->table('clients')->where('user_id', auth()->id())->first();
        $inv = $invoiceId > 0
            ? db()->table('invoices')->where('id', $invoiceId)->first()
            : null;

        $this->authorize(
            $inv !== null && $client !== null && (int) $inv['client_id'] === (int) $client['id']
        );

        return [$inv, $client];
    }

    /**
     * Get the current user's client row, creating one from the user
     * record if it does not yet exist.
     */
    protected function currentClient(int $tenantId): array
    {
        $client = db()->table('clients')->where('user_id', auth()->id())->first();
        if ($client) {
            return $client;
        }

        $user = auth()->user() ?? [];
        $names = explode(' ', trim((string) ($user['name'] ?? 'Client')), 2);
        $now = date('Y-m-d H:i:s');
        $userId = (int) auth()->id();

        $clientId = db()->table('clients')->insert([
            'tenant_id'   => $tenantId,
            'user_id'     => $userId,
            'client_code' => 'C' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT),
            'first_name'  => $names[0] !== '' ? $names[0] : 'Client',
            'last_name'   => $names[1] ?? '',
            'email'       => (string) ($user['email'] ?? ''),
            'phone'       => (string) ($user['mobile'] ?? ''),
            'status'      => 'active',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return db()->table('clients')->where('id', $clientId)->first();
    }

    /**
     * Next sequential order number: ORD-000123.
     */
    protected function nextOrderNumber(): string
    {
        $last = db()->table('orders')->orderBy('id', 'desc')->first();
        $seq = $last ? ((int) preg_replace('/\D/', '', (string) $last['order_number'])) + 1 : 1;
        return 'ORD-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Render a self-contained Razorpay hosted-checkout page. On success
     * the webhook confirms payment server-side; the browser is sent to
     * the success page.
     */
    protected function razorpayCheckout(array $fields, int $invoiceId): Response
    {
        $options = [
            'key'         => (string) ($fields['key'] ?? ''),
            'amount'      => (int) ($fields['amount'] ?? 0),
            'currency'    => (string) ($fields['currency'] ?? 'INR'),
            'order_id'    => (string) ($fields['order_id'] ?? ''),
            'name'        => (string) ($fields['name'] ?? 'AK Cloud'),
            'description' => (string) ($fields['description'] ?? ''),
            'prefill'     => [
                'name'    => (string) ($fields['prefill_name'] ?? ''),
                'email'   => (string) ($fields['prefill_email'] ?? ''),
                'contact' => (string) ($fields['prefill_contact'] ?? ''),
            ],
            'theme'       => ['color' => '#2563eb'],
        ];

        $optsJson    = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $successJson = json_encode(url('order/success/' . $invoiceId), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $backJson    = json_encode(url('order/checkout/' . $invoiceId), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $company     = e(settings('general.company_name', 'AK Cloud'));

        $html = '<!doctype html><html lang="gu"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Razorpay — ' . $company . '</title>'
            . '<style>body{font-family:system-ui,"Noto Sans Gujarati",sans-serif;background:#f1f5f9;color:#0f172a;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:20px}'
            . '.box{max-width:420px}a{color:#2563eb}</style></head><body>'
            . '<div class="box"><h2>☁️ ' . $company . '</h2>'
            . '<p>Razorpay ચૂકવણી પેજ ખૂલી રહ્યું છે…</p>'
            . '<p class="muted">જો window ના ખૂલે તો <a id="retry" href="#">અહીં ક્લિક કરો</a>.</p>'
            . '<p><a id="cancel" href="#">← પાછા ઇન્વૉઇસ પર</a></p></div>'
            . '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>'
            . '<script>'
            . 'var opts=' . $optsJson . ';'
            . 'var successUrl=' . $successJson . ';'
            . 'var backUrl=' . $backJson . ';'
            . 'document.getElementById("cancel").href=backUrl;'
            . 'opts.handler=function(resp){window.location=successUrl;};'
            . 'opts.modal={ondismiss:function(){window.location=backUrl;}};'
            . 'function openRzp(){if(window.Razorpay){var rzp=new Razorpay(opts);rzp.open();}else{window.location=backUrl;}}'
            . 'document.getElementById("retry").addEventListener("click",function(e){e.preventDefault();openRzp();});'
            . 'openRzp();'
            . '</script></body></html>';

        return Response::make($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
