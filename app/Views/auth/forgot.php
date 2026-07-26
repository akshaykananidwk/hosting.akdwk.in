<?php $this->extend('layouts/guest'); $this->set('title', 'Forgot Password'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">Reset Password</h3>
<p class="muted small mb-3">Enter your email — a reset link will be sent by email and WhatsApp.</p>
<form method="post" action="<?= e(url('forgot')) ?>">
    <?= csrf_field() ?>
    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
    <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
</form>
<div class="text-center mt-3 small"><a href="<?= e(url('login')) ?>">← Back to sign in</a></div>
<?php $this->end(); ?>
