<?php /* FILE: /app/Views/store/success.php — order thank-you page */ ?>
<?php $this->extend('layouts/guest'); $this->set('title', 'Thanks'); ?>
<?php $this->section('content'); ?>
<div class="text-center">
    <div style="font-size:3rem">✅</div>
    <h3>Thank you!</h3>
    <p class="muted">
        We have received your order — invoice <strong><?= e($invoice['invoice_number']) ?></strong>
        (Total <?= e(money($invoice['total'])) ?>).
    </p>

    <div class="alert alert-info mt-3">
        Once payment is confirmed your hosting will be provisioned <strong>automatically</strong>.
        You will receive the login details by WhatsApp / email within a few minutes.
    </div>

    <div class="mt-3">
        <a href="<?= e(url('client')) ?>" class="btn btn-primary">Go to dashboard</a>
        <a href="<?= e(url('store')) ?>" class="btn btn-outline">Place another order</a>
    </div>
</div>
<?php $this->end(); ?>
