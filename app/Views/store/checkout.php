<?php /* FILE: /app/Views/store/checkout.php — invoice + payment options */ ?>
<?php $this->extend('layouts/guest'); $this->set('title', 'Checkout'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">Checkout — <?= e($invoice['invoice_number']) ?></h3>

<div class="card"><div class="card-body">
    <div class="table-wrap"><table class="table">
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= e($it['description']) ?></td>
                    <td class="text-right"><?= e(money($it['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td>Subtotal</td>
                <td class="text-right"><?= e(money($invoice['subtotal'])) ?></td>
            </tr>
            <?php if ((float) $invoice['discount'] > 0): ?>
                <tr>
                    <td>Discount</td>
                    <td class="text-right">- <?= e(money($invoice['discount'])) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ((float) $invoice['cgst'] > 0): ?>
                <tr><td>CGST</td><td class="text-right"><?= e(money($invoice['cgst'])) ?></td></tr>
            <?php endif; ?>
            <?php if ((float) $invoice['sgst'] > 0): ?>
                <tr><td>SGST</td><td class="text-right"><?= e(money($invoice['sgst'])) ?></td></tr>
            <?php endif; ?>
            <?php if ((float) $invoice['igst'] > 0): ?>
                <tr><td>IGST</td><td class="text-right"><?= e(money($invoice['igst'])) ?></td></tr>
            <?php endif; ?>
            <?php if ((float) $invoice['tax_total'] > 0): ?>
                <tr><td>GST Total</td><td class="text-right"><?= e(money($invoice['tax_total'])) ?></td></tr>
            <?php endif; ?>
            <tr>
                <td><strong>Total payable</strong></td>
                <td class="text-right"><strong><?= e(money($invoice['total'])) ?></strong></td>
            </tr>
        </tbody>
    </table></div>
</div></div>

<?php if (empty($gateways)): ?>
    <div class="alert alert-info">
        No online gateway is active right now. Please pay by bank transfer and quote
        invoice number <strong><?= e($invoice['invoice_number']) ?></strong> Please contact support.
    </div>
<?php else: ?>
    <?php foreach ($gateways as $g): ?>
        <?php if (($g['driver'] ?? '') === 'razorpay'): ?>
            <div class="card"><div class="card-body">
                <h4>💳 <?= e($g['name'] ?? 'Razorpay') ?></h4>
                <p class="muted small">Card / UPI / Netbanking — Secure online payment.</p>
                <form method="post" action="<?= e(url('order/pay/' . $invoice['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="gateway" value="razorpay">
                    <button type="submit" class="btn btn-primary btn-block">
                        Razorpay Pay with (<?= e(money($invoice['total'])) ?>)
                    </button>
                </form>
            </div></div>
        <?php elseif (($g['driver'] ?? '') === 'upi_manual' && !empty($upi)): ?>
            <div class="card"><div class="card-body">
                <h4>📲 <?= e($g['name'] ?? 'UPI') ?></h4>
                <p class="small">UPI ID: <strong class="mono"><?= e($upi['upi_id']) ?></strong></p>
                <p class="small">Amount: <strong><?= e(money($invoice['total'])) ?></strong></p>
                <p class="small mono" style="word-break:break-all">UPI Link: <?= e($upi['upi_uri']) ?></p>
                <p class="muted small"><?= e($upi['note']) ?></p>
                <form method="post" action="<?= e(url('order/pay/' . $invoice['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="gateway" value="upi_manual">
                    <div class="form-group">
                        <label>UTR / Transaction ID</label>
                        <input type="text" name="utr" placeholder="12-digit UTR" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-block">Submit Payment</button>
                </form>
            </div></div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="text-center small mt-2">
    <a href="<?= e(url('client/invoices/' . $invoice['id'])) ?>">Invoice Details</a>
    &nbsp;·&nbsp;
    <a href="<?= e(url('store')) ?>">Store</a>
</div>
<?php $this->end(); ?>
