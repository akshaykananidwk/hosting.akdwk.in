<?php
// FILE: /app/Services/Gateways/RazorpayGateway.php
// -------------------------------------------------------------------
// Razorpay driver (default). Order create via API + webhook/handler
// signature verification (HMAC-SHA256). Keys `payment_gateways.config`
// માંથી (encrypted).
// -------------------------------------------------------------------

namespace App\Services\Gateways;

use App\Core\App;

class RazorpayGateway implements GatewayInterface
{
    protected string $keyId;
    protected string $keySecret;

    public function __construct(array $config)
    {
        $this->keyId = (string) ($config['key_id'] ?? '');
        $this->keySecret = (string) ($config['key_secret'] ?? '');
    }

    public function name(): string
    {
        return 'razorpay';
    }

    public function initiate(array $invoice, array $client): array
    {
        // Razorpay amounts are in paise.
        $amount = (int) round(((float) $invoice['total']) * 100);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => $amount, 'currency' => 'INR',
                'receipt' => $invoice['invoice_number'],
                'notes' => ['invoice_id' => $invoice['id']],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $resp = json_decode((string) curl_exec($ch), true);
        curl_close($ch);

        return [
            'order_ref' => $resp['id'] ?? null,
            'fields' => [
                'key' => $this->keyId,
                'amount' => $amount,
                'currency' => 'INR',
                'order_id' => $resp['id'] ?? '',
                'name' => settings('general.company_name', 'AK Cloud'),
                'description' => 'Invoice ' . $invoice['invoice_number'],
                'prefill_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
                'prefill_email' => $client['email'] ?? '',
                'prefill_contact' => $client['phone'] ?? '',
            ],
        ];
    }

    /**
     * Verify either the checkout handler response (order_id|payment_id|
     * signature) or a webhook (X-Razorpay-Signature over raw body).
     */
    public function verify(array $payload, array $headers = []): ?string
    {
        // Checkout handler flow.
        if (isset($payload['razorpay_order_id'], $payload['razorpay_payment_id'], $payload['razorpay_signature'])) {
            $expected = hash_hmac('sha256', $payload['razorpay_order_id'] . '|' . $payload['razorpay_payment_id'], $this->keySecret);
            if (hash_equals($expected, $payload['razorpay_signature'])) {
                return $payload['razorpay_payment_id'];
            }
            return null;
        }
        // Webhook flow — signature over the raw JSON body.
        $sig = $headers['X-Razorpay-Signature'] ?? ($payload['_raw_signature'] ?? '');
        $raw = $payload['_raw_body'] ?? '';
        if ($sig && $raw) {
            $expected = hash_hmac('sha256', $raw, $this->keySecret);
            if (hash_equals($expected, $sig)) {
                $body = json_decode($raw, true);
                return $body['payload']['payment']['entity']['id'] ?? 'webhook';
            }
        }
        return null;
    }
}
