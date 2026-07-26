<?php /* FILE: /app/Views/store/success.php — order thank-you page */ ?>
<?php $this->extend('layouts/guest'); $this->set('title', 'આભાર'); ?>
<?php $this->section('content'); ?>
<div class="text-center">
    <div style="font-size:3rem">✅</div>
    <h3>આભાર!</h3>
    <p class="muted">
        તમારો ઓર્ડર મળ્યો છે — ઇન્વૉઇસ <strong><?= e($invoice['invoice_number']) ?></strong>
        (કુલ <?= e(money($invoice['total'])) ?>).
    </p>

    <div class="alert alert-info mt-3">
        ચૂકવણી કન્ફર્મ થયા પછી તમારું હોસ્ટિંગ <strong>આપમેળે (automatic)</strong> provision થઈ જશે.
        થોડી જ મિનિટોમાં WhatsApp / Email પર login વિગતો મળી જશે.
    </div>

    <div class="mt-3">
        <a href="<?= e(url('client')) ?>" class="btn btn-primary">ડેશબોર્ડ પર જાઓ</a>
        <a href="<?= e(url('store')) ?>" class="btn btn-outline">બીજું ઓર્ડર કરો</a>
    </div>
</div>
<?php $this->end(); ?>
