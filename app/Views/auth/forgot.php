<?php $this->extend('layouts/guest'); $this->set('title', 'Forgot Password'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">પાસવર્ડ રીસેટ</h3>
<p class="muted small mb-3">તમારું ઈમેલ નાખો — reset link ઈમેલ + WhatsApp પર મળશે.</p>
<form method="post" action="<?= e(url('forgot')) ?>">
    <?= csrf_field() ?>
    <div class="form-group"><label>ઈમેલ</label><input type="email" name="email" required></div>
    <button type="submit" class="btn btn-primary btn-block">Reset link મોકલો</button>
</form>
<div class="text-center mt-3 small"><a href="<?= e(url('login')) ?>">← લોગિન પર પાછા</a></div>
<?php $this->end(); ?>
