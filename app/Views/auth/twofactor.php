<?php $this->extend('layouts/guest'); $this->set('title', '2FA'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">Two-Factor Verification</h3>
<p class="muted small mb-3">Enter the 6-digit code from your authenticator app or WhatsApp.</p>
<form method="post" action="<?= e(url('login/2fa')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6"
               style="text-align:center;font-size:1.5rem;letter-spacing:.3em" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Verify</button>
</form>
<?php $this->end(); ?>
