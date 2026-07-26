<?php
// FILE: /app/Services/PaymentService.php
// -------------------------------------------------------------------
// Gateway resolver: returns a configured gateway instance for a driver
// name (keys read from `payment_gateways.config`, encrypted). Module 8.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;
use App\Services\Gateways\GatewayInterface;
use App\Services\Gateways\ManualUpiGateway;
use App\Services\Gateways\RazorpayGateway;

class PaymentService
{
    /** @var array<string,class-string<GatewayInterface>> */
    protected array $drivers = [
        'razorpay' => RazorpayGateway::class,
        'upi_manual' => ManualUpiGateway::class,
    ];

    public function driver(string $name): ?GatewayInterface
    {
        $row = App::instance()->make('db')->table('payment_gateways')
            ->where('driver', $name)->where('is_active', 1)->first();
        if (!$row || !isset($this->drivers[$name])) {
            return null;
        }
        $config = [];
        if (!empty($row['config'])) {
            $decoded = App::instance()->make('crypt')->decrypt($row['config']);
            $config = $decoded ? (json_decode($decoded, true) ?: []) : [];
        }
        $class = $this->drivers[$name];
        return new $class($config);
    }

    public function activeGateways(): array
    {
        return App::instance()->make('db')->table('payment_gateways')
            ->where('is_active', 1)->orderBy('sort_order', 'asc')->get();
    }

    public function defaultGateway(): ?array
    {
        return App::instance()->make('db')->table('payment_gateways')
            ->where('is_active', 1)->where('is_default', 1)->first();
    }
}
