<?php
// FILE: /app/Services/BillingService.php
// -------------------------------------------------------------------
// MODULE 8 — Billing engine. Invoice generation with GST (CGST/SGST vs
// IGST), sequential numbers, mark-paid → unsuspend/provision, overdue
// suspend, late fee, credit application.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class BillingService
{
    protected $db;

    public function __construct()
    {
        $this->db = App::instance()->make('db');
    }

    /**
     * Next sequential invoice number: AKC-000123.
     */
    public function nextInvoiceNumber(): string
    {
        $prefix = (string) settings('billing.invoice_prefix', 'AKC-');
        $last = $this->db->table('invoices')->orderBy('id', 'desc')->first();
        $seq = $last ? ((int) preg_replace('/\D/', '', (string) $last['invoice_number'])) + 1 : 1;
        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create an invoice for a set of line items for a client.
     *
     * @param array $lines each: ['description','service_id'?,'amount','hsn_sac'?,'qty'?,'period_start'?,'period_end'?]
     */
    public function createInvoice(array $client, array $lines, array $opts = []): int
    {
        $gstEnabled = (string) settings('tax.gst_enabled', '1') === '1';
        $rate = (float) settings('tax.gst_percent', 18);
        $companyState = (string) settings('tax.company_state_code', '24');
        $sameState = $gstEnabled && ($client['state_code'] ?? $companyState) === $companyState;

        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += ((float) $line['amount']) * (int) ($line['qty'] ?? 1);
        }
        $discount = (float) ($opts['discount'] ?? 0);
        $taxable = max(0, $subtotal - $discount);

        $tax = ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'total' => 0.0];
        if ($gstEnabled) {
            $tax = gst_split($taxable, $rate, $sameState);
        }
        $total = $taxable + $tax['total'];

        $dueDays = (int) ($opts['due_days'] ?? 0);
        $invoiceId = $this->db->table('invoices')->insert([
            'tenant_id' => $client['tenant_id'],
            'client_id' => $client['id'],
            'invoice_number' => $this->nextInvoiceNumber(),
            'status' => $opts['status'] ?? 'unpaid',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'cgst' => $tax['cgst'], 'sgst' => $tax['sgst'], 'igst' => $tax['igst'],
            'tax_total' => $tax['total'],
            'total' => $total,
            'currency' => 'INR',
            'place_of_supply' => $client['state_code'] ?? $companyState,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', time() + $dueDays * 86400),
            'notes' => $opts['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($lines as $line) {
            $this->db->table('invoice_items')->insert([
                'tenant_id' => $client['tenant_id'],
                'invoice_id' => $invoiceId,
                'service_id' => $line['service_id'] ?? null,
                'description' => $line['description'],
                'hsn_sac' => $line['hsn_sac'] ?? '998315',
                'qty' => (int) ($line['qty'] ?? 1),
                'unit_price' => (float) $line['amount'],
                'tax_rate' => $gstEnabled ? $rate : 0,
                'amount' => ((float) $line['amount']) * (int) ($line['qty'] ?? 1),
                'period_start' => $line['period_start'] ?? null,
                'period_end' => $line['period_end'] ?? null,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $invoiceId;
    }

    /**
     * Generate a renewal invoice for a service due within N days.
     */
    public function generateRenewalInvoice(array $service): ?int
    {
        $client = $this->db->table('clients')->where('id', (int) $service['client_id'])->first();
        if (!$client) {
            return null;
        }
        $product = $service['product_id'] ? $this->db->table('products')->where('id', (int) $service['product_id'])->first() : null;
        $desc = ($product['name'] ?? 'Hosting') . ' — ' . $service['domain'] . ' (' . $service['billing_cycle'] . ')';
        return $this->createInvoice($client, [[
            'description' => $desc,
            'service_id' => $service['id'],
            'amount' => (float) $service['recurring_amount'],
            'period_start' => $service['next_due_date'],
            'period_end' => $this->addCycle($service['next_due_date'], $service['billing_cycle']),
        ]], ['due_days' => 0, 'notes' => 'Renewal invoice']);
    }

    public function addCycle(?string $date, string $cycle): string
    {
        $base = $date ? strtotime($date) : time();
        $map = [
            'monthly' => '+1 month', 'quarterly' => '+3 months',
            'half_yearly' => '+6 months', 'yearly' => '+1 year',
        ];
        return date('Y-m-d', strtotime($map[$cycle] ?? '+1 month', $base));
    }

    /**
     * Mark an invoice paid, record a transaction, and activate/renew
     * the linked service.
     */
    public function markPaid(int $invoiceId, string $gateway, ?string $txnRef = null, ?float $amount = null): bool
    {
        $invoice = $this->db->table('invoices')->where('id', $invoiceId)->first();
        if (!$invoice || $invoice['status'] === 'paid') {
            return false;
        }
        $amount ??= (float) $invoice['total'];

        return $this->db->transaction(function () use ($invoice, $invoiceId, $gateway, $txnRef, $amount) {
            $this->db->table('invoices')->where('id', $invoiceId)->update([
                'status' => 'paid', 'paid_amount' => $amount, 'paid_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('transactions')->insert([
                'tenant_id' => $invoice['tenant_id'],
                'invoice_id' => $invoiceId, 'client_id' => $invoice['client_id'],
                'gateway' => $gateway, 'transaction_id' => $txnRef, 'gateway_ref' => $txnRef,
                'type' => 'payment', 'amount' => $amount, 'currency' => 'INR',
                'status' => 'success', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->activateServicesForInvoice($invoice);
            $this->notifyPayment($invoice, $amount);
            return true;
        });
    }

    protected function activateServicesForInvoice(array $invoice): void
    {
        $items = $this->db->table('invoice_items')->where('invoice_id', (int) $invoice['id'])->whereNotNull('service_id')->get();
        $prov = new ProvisioningService();
        foreach ($items as $item) {
            $service = $this->db->table('services')->where('id', (int) $item['service_id'])->first();
            if (!$service) {
                continue;
            }
            if ($service['status'] === 'pending') {
                $prov->enqueue((int) $service['id'], 'create');
            } elseif ($service['status'] === 'suspended') {
                $prov->enqueue((int) $service['id'], 'unsuspend');
            }
            // Advance the due date on renewal.
            if (in_array($service['status'], ['active', 'suspended'], true)) {
                $this->db->table('services')->where('id', (int) $service['id'])->update([
                    'next_due_date' => $this->addCycle($service['next_due_date'], $service['billing_cycle']),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    protected function notifyPayment(array $invoice, float $amount): void
    {
        $client = $this->db->table('clients')->where('id', (int) $invoice['client_id'])->first();
        if ($client && !empty($client['phone'])) {
            (new WhatsAppService())->sendTemplate('payment_received', $client['phone'], [
                'client_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
                'invoice_no' => $invoice['invoice_number'],
                'amount' => number_format($amount, 2),
                'company_name' => settings('general.company_name', 'AK Cloud'),
            ]);
        }
    }

    /**
     * Suspend services whose invoices are overdue beyond the grace period.
     */
    public function suspendOverdue(): int
    {
        $grace = (int) settings('billing.grace_days', 3);
        $cutoff = date('Y-m-d', time() - $grace * 86400);
        $overdue = $this->db->table('invoices')
            ->where('status', 'unpaid')
            ->where('due_date', '<', $cutoff)
            ->get();
        $count = 0;
        $prov = new ProvisioningService();
        foreach ($overdue as $invoice) {
            $this->db->table('invoices')->where('id', (int) $invoice['id'])->update(['status' => 'overdue']);
            $items = $this->db->table('invoice_items')->where('invoice_id', (int) $invoice['id'])->whereNotNull('service_id')->get();
            foreach ($items as $item) {
                $service = $this->db->table('services')->where('id', (int) $item['service_id'])->first();
                if ($service && $service['status'] === 'active') {
                    $prov->suspend((int) $service['id'], 'Invoice overdue: ' . $invoice['invoice_number']);
                    $count++;
                }
            }
        }
        return $count;
    }
}
