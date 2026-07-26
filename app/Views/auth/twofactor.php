<?php $this->extend('layouts/guest'); $this->set('title', '2FA'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">દ્વિ-પગલાં ચકાસણી</h3>
<p class="muted small mb-3">તમારા authenticator app અથવા WhatsApp પર આવેલો 6-અંકનો કોડ નાખો.</p>
<form method="post" action="<?= e(url('login/2fa')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6"
               style="text-align:center;font-size:1.5rem;letter-spacing:.3em" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block">ચકાસો</button>
</form>
<?php $this->end(); ?>
