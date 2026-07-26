<?php
// FILE: /app/Services/Gateways/ManualUpiGateway.php
// -------------------------------------------------------------------
// Manual UPI driver — QR બતાવે + client UTR submit કરે → admin approve.
// verify() અહીં null આપે (admin જ manually approve કરે).
// -------------------------------------------------------------------

namespace App\Services\Gateways;

class ManualUpiGateway implements GatewayInterface
{
    protected string $upiId;
    protected string $payeeName;

    public function __construct(array $config)
    {
        $this->upiId = (string) ($config['upi_id'] ?? '');
        $this->payeeName = (string) ($config['payee_name'] ?? settings('general.company_name', 'AK Cloud'));
    }

    public function name(): string
    {
        return 'upi_manual';
    }

    public function initiate(array $invoice, array $client): array
    {
        $amount = number_format((float) $invoice['total'], 2, '.', '');
        $upiUri = 'upi://pay?pa=' . rawurlencode($this->upiId)
            . '&pn=' . rawurlencode($this->payeeName)
            . '&am=' . $amount
            . '&cu=INR&tn=' . rawurlencode('Invoice ' . $invoice['invoice_number']);

        return [
            'fields' => [
                'upi_id' => $this->upiId,
                'payee' => $this->payeeName,
                'amount' => $amount,
                'upi_uri' => $upiUri,
                'note' => 'ચુકવણી પછી UTR/Transaction ID નાખો — admin verify કરશે.',
            ],
        ];
    }

    public function verify(array $payload, array $headers = []): ?string
    {
        // Manual — no automatic verification; admin approves the UTR.
        return null;
    }
}
