<?php /* FILE: /app/Views/client/invoices/show.php — printable invoice with GST */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Invoice ' . ($invoice['invoice_number'] ?? '')); ?>
<?php $this->section('content'); ?>
<?php
$isUnpaid = in_array($invoice['status'], ['unpaid', 'overdue'], true);
$statusBadge = match ($invoice['status']) {
    'paid'    => 'badge-success',
    'unpaid'  => 'badge-warning',
    'overdue' => 'badge-danger',
    'draft'   => 'badge-info',
    default   => 'badge-muted',
};
$company   = (string) settings('general.company_name', 'AK Cloud');
$balance   = (float) $invoice['total'] - (float) $invoice['paid_amount'] - (float) $invoice['credit_used'];
?>

<div class="flex items-center justify-between mb-3">
    <a class="btn btn-sm btn-outline" href="<?= e(url('client/invoices')) ?>">← All Invoices</a>
    <div class="flex gap-2">
        <button class="btn btn-sm btn-outline" onclick="window.print()">🖨️ Print</button>
        <?php if ($isUnpaid): ?>
            <a class="btn btn-sm btn-primary" href="<?= e(url('order/checkout/' . $invoice['id'])) ?>">Pay Now</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h2 style="margin:0"><?= e($company) ?></h2>
            <div class="small muted">Invoice <?= e($invoice['invoice_number']) ?></div>
        </div>
        <div class="text-right">
            <span class="badge <?= e($statusBadge) ?>"><?= e($invoice['status']) ?></span>
            <div class="small muted mt-1">Issue: <?= e($invoice['issue_date'] ? date('d M Y', strtotime((string) $invoice['issue_date'])) : '—') ?></div>
            <div class="small muted">Due: <?= e($invoice['due_date'] ? date('d M Y', strtotime((string) $invoice['due_date'])) : '—') ?></div>
        </div>
    </div>

    <div class="grid cols-2 mb-3">
        <div>
            <h4 style="margin:0 0 4px">Bill To</h4>
            <div><strong><?= e(trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''))) ?></strong></div>
            <?php if (!empty($client['company'])): ?><div class="small"><?= e($client['company']) ?></div><?php endif; ?>
            <div class="small muted"><?= e($client['email'] ?? '') ?></div>
            <?php if (!empty($client['address'])): ?><div class="small muted"><?= e($client['address']) ?>, <?= e($client['city'] ?? '') ?> <?= e($client['state'] ?? '') ?> <?= e($client['postcode'] ?? '') ?></div><?php endif; ?>
            <?php if (!empty($client['gstin'])): ?><div class="small">GSTIN: <?= e($client['gstin']) ?></div><?php endif; ?>
        </div>
        <div class="text-right">
            <h4 style="margin:0 0 4px">Place of Supply</h4>
            <div class="small"><?= e($invoice['place_of_supply'] ?: ($client['state_code'] ?? '—')) ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>HSN/SAC</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit</th>
                    <th class="text-right">Tax %</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="muted">No line items.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['description']) ?>
                                <?php if (!empty($it['period_start'])): ?>
                                    <div class="small muted"><?= e(date('d M Y', strtotime((string) $it['period_start']))) ?> — <?= e($it['period_end'] ? date('d M Y', strtotime((string) $it['period_end'])) : '') ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($it['hsn_sac'] ?: '—') ?></td>
                            <td class="text-right"><?= e((string) $it['qty']) ?></td>
                            <td class="text-right"><?= e(money($it['unit_price'])) ?></td>
                            <td class="text-right"><?= e(number_format((float) $it['tax_rate'], 2)) ?></td>
                            <td class="text-right"><?= e(money($it['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-between mt-3" style="flex-wrap:wrap;gap:16px">
        <div class="small muted" style="max-width:340px">
            <?php if (!empty($invoice['notes'])): ?><strong>Notes:</strong> <?= nl2br(e($invoice['notes'])) ?><?php endif; ?>
        </div>
        <div style="min-width:260px">
            <table class="table">
                <tbody>
                    <tr><th>Subtotal</th><td class="text-right"><?= e(money($invoice['subtotal'])) ?></td></tr>
                    <?php if ((float) $invoice['discount'] > 0): ?>
                        <tr><th>Discount</th><td class="text-right">− <?= e(money($invoice['discount'])) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['cgst'] > 0): ?>
                        <tr><th>CGST</th><td class="text-right"><?= e(money($invoice['cgst'])) ?></td></tr>
                        <tr><th>SGST</th><td class="text-right"><?= e(money($invoice['sgst'])) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['igst'] > 0): ?>
                        <tr><th>IGST</th><td class="text-right"><?= e(money($invoice['igst'])) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['late_fee'] > 0): ?>
                        <tr><th>Late Fee</th><td class="text-right"><?= e(money($invoice['late_fee'])) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Total</th><td class="text-right"><strong><?= e(money($invoice['total'])) ?></strong></td></tr>
                    <?php if ((float) $invoice['paid_amount'] > 0): ?>
                        <tr><th>Paid</th><td class="text-right">− <?= e(money($invoice['paid_amount'])) ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float) $invoice['credit_used'] > 0): ?>
                        <tr><th>Credit Used</th><td class="text-right">− <?= e(money($invoice['credit_used'])) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Balance Due</th><td class="text-right"><strong><?= e(money(max(0, $balance))) ?></strong></td></tr>
                </tbody>
            </table>
            <?php if ($isUnpaid): ?>
                <a class="btn btn-primary btn-block mt-2" href="<?= e(url('order/checkout/' . $invoice['id'])) ?>">Pay Now — <?= e(money(max(0, $balance))) ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $this->end(); ?>
