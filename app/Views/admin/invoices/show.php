<?php // FILE: /app/Views/admin/invoices/show.php — printable GST invoice
$this->extend('layouts/admin'); $this->set('title', 'Invoice ' . ($invoice['invoice_number'] ?? ''));
$iBadge = ['paid' => 'success', 'unpaid' => 'warning', 'overdue' => 'danger', 'draft' => 'muted', 'cancelled' => 'muted', 'refunded' => 'info'];
$companyName = settings('general.company_name', 'AK Cloud');
$companyGstin = settings('tax.company_gstin', '');
$companyState = settings('tax.company_state_code', '');
$clientName = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
$taxable = round((float) $invoice['subtotal'] - (float) $invoice['discount'], 2);
$tBadge = ['success' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'refunded' => 'info'];
?>

<style>
@media print {
    .sidebar, .topbar, .no-print { display: none !important; }
    .content { margin-left: 0 !important; }
    .main { padding: 0 !important; }
    .invoice-doc { box-shadow: none !important; border: 0 !important; }
    body { background: #fff !important; }
}
.invoice-doc .inv-head { display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.invoice-doc table.items th, .invoice-doc table.items td { padding:9px 12px; }
.inv-totals { margin-left:auto; max-width:340px; }
.inv-totals .row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid var(--border); }
.inv-totals .row.grand { font-weight:700; font-size:1.1rem; border-bottom:0; }
</style>

<div class="flex gap-1 mb-3 no-print" style="flex-wrap:wrap">
    <a href="<?= e(url('admin/invoices')) ?>" class="btn btn-outline btn-sm">‹ Invoices</a>
    <button class="btn btn-outline btn-sm" onclick="window.print()">🖨️ Print</button>
    <?php if (($invoice['status'] ?? '') !== 'paid' && ($invoice['status'] ?? '') !== 'cancelled'): ?>
        <form method="post" action="<?= e(url('admin/invoices/' . $invoice['id'] . '/markpaid')) ?>" data-confirm="Paid Mark as paid?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success btn-sm">✔ Mark Paid</button>
        </form>
    <?php endif; ?>
    <span class="badge badge-<?= $iBadge[$invoice['status']] ?? 'muted' ?>" style="align-self:center"><?= e($invoice['status']) ?></span>
</div>

<div class="card invoice-doc">
    <div class="card-body">
        <div class="inv-head mb-3">
            <div>
                <h2 style="margin-bottom:2px"><?= e($companyName) ?></h2>
                <?php if ($companyGstin): ?><div class="small">GSTIN: <span class="mono"><?= e($companyGstin) ?></span></div><?php endif; ?>
                <?php if ($companyState): ?><div class="small muted">State Code: <?= e($companyState) ?></div><?php endif; ?>
            </div>
            <div class="text-right">
                <h3 style="margin-bottom:2px">TAX INVOICE</h3>
                <div class="mono"><?= e($invoice['invoice_number']) ?></div>
                <div class="small muted">Issue: <?= e($invoice['issue_date']) ?></div>
                <div class="small muted">Due: <?= e($invoice['due_date']) ?></div>
            </div>
        </div>

        <div class="grid cols-2 mb-3">
            <div>
                <div class="muted small">Bill To</div>
                <strong><?= e($clientName ?: ($client['company'] ?? '—')) ?></strong>
                <?php if (!empty($client['company']) && $clientName): ?><div class="small"><?= e($client['company']) ?></div><?php endif; ?>
                <?php if (!empty($client['email'])): ?><div class="small"><?= e($client['email']) ?></div><?php endif; ?>
                <?php if (!empty($client['phone'])): ?><div class="small"><?= e($client['phone']) ?></div><?php endif; ?>
                <?php if (!empty($client['gstin'])): ?><div class="small">GSTIN: <span class="mono"><?= e($client['gstin']) ?></span></div><?php endif; ?>
            </div>
            <div class="text-right">
                <div class="muted small">Place of Supply</div>
                <div><?= e($invoice['place_of_supply'] ?? '—') ?></div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table items">
                <thead><tr><th>#</th><th>Description</th><th>HSN/SAC</th><th>Qty</th><th>Rate</th><th>Tax %</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($it['description']) ?>
                                <?php if (!empty($it['period_start'])): ?><div class="small muted"><?= e($it['period_start']) ?> → <?= e($it['period_end'] ?? '') ?></div><?php endif; ?>
                            </td>
                            <td class="mono small"><?= e($it['hsn_sac'] ?? '—') ?></td>
                            <td><?= (int) $it['qty'] ?></td>
                            <td><?= e(money($it['unit_price'])) ?></td>
                            <td class="small"><?= e(rtrim(rtrim((string) $it['tax_rate'], '0'), '.')) ?>%</td>
                            <td class="text-right"><?= e(money($it['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?><tr><td colspan="7" class="text-center muted">No items</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="inv-totals mt-3">
            <div class="row"><span>Subtotal</span><span><?= e(money($invoice['subtotal'])) ?></span></div>
            <?php if ((float) $invoice['discount'] > 0): ?><div class="row"><span>Discount</span><span>- <?= e(money($invoice['discount'])) ?></span></div><?php endif; ?>
            <div class="row"><span>Taxable Value</span><span><?= e(money($taxable)) ?></span></div>
            <?php if ((float) $invoice['cgst'] > 0): ?><div class="row"><span>CGST</span><span><?= e(money($invoice['cgst'])) ?></span></div><?php endif; ?>
            <?php if ((float) $invoice['sgst'] > 0): ?><div class="row"><span>SGST</span><span><?= e(money($invoice['sgst'])) ?></span></div><?php endif; ?>
            <?php if ((float) $invoice['igst'] > 0): ?><div class="row"><span>IGST</span><span><?= e(money($invoice['igst'])) ?></span></div><?php endif; ?>
            <?php if ((float) ($invoice['late_fee'] ?? 0) > 0): ?><div class="row"><span>Late Fee</span><span><?= e(money($invoice['late_fee'])) ?></span></div><?php endif; ?>
            <div class="row grand"><span>Total</span><span><?= e(money($invoice['total'])) ?></span></div>
            <?php if ((float) ($invoice['paid_amount'] ?? 0) > 0): ?><div class="row"><span>Paid</span><span><?= e(money($invoice['paid_amount'])) ?></span></div><?php endif; ?>
        </div>

        <?php if (!empty($invoice['notes'])): ?>
            <div class="mt-3 small muted">Note: <?= e($invoice['notes']) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card no-print">
    <div class="card-head">Transactions</div>
    <div class="card-body table-wrap">
        <table class="table">
            <thead><tr><th>#</th><th>Gateway</th><th>Type</th><th>Amount</th><th>UTR / Ref</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (empty($transactions)): ?><tr><td colspan="7" class="text-center muted">No transactions</td></tr><?php endif; ?>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= (int) $t['id'] ?></td>
                        <td><?= e($t['gateway']) ?></td>
                        <td class="small"><?= e($t['type']) ?></td>
                        <td><?= e(money($t['amount'])) ?></td>
                        <td class="mono small"><?= e($t['utr'] ?: ($t['gateway_ref'] ?? '—')) ?></td>
                        <td><span class="badge badge-<?= $tBadge[$t['status']] ?? 'muted' ?>"><?= e($t['status']) ?></span></td>
                        <td class="small"><?= e($t['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

