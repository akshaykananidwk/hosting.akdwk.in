<?php
// FILE: /app/Services/Gateways/GatewayInterface.php
// -------------------------------------------------------------------
// Payment gateway driver contract (Module 8). દરેક gateway આ
// implement કરે જેથી checkout/webhook code એકસરખું રહે.
// -------------------------------------------------------------------

namespace App\Services\Gateways;

interface GatewayInterface
{
    /**
     * Driver machine name (matches payment_gateways.driver).
     */
    public function name(): string;

    /**
     * Create a payment intent/order and return data the checkout page
     * needs (redirect URL, order id, key, etc.).
     *
     * @return array{redirect?:string,fields?:array,order_ref?:string,html?:string}
     */
    public function initiate(array $invoice, array $client): array;

    /**
     * Verify a callback/webhook payload signature and return the
     * gateway transaction ref on success, or null on failure.
     */
    public function verify(array $payload, array $headers = []): ?string;
}
