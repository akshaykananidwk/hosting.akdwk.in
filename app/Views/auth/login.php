<?php $this->extend('layouts/guest'); $this->set('title', 'Login'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">લોગિન કરો</h3>
<form method="post" action="<?= e(url('login')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label>ઈમેલ</label>
        <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
    </div>
    <div class="form-group">
        <label>પાસવર્ડ</label>
        <input type="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">લોગિન</button>
</form>
<div class="flex justify-between mt-3 small">
    <a href="<?= e(url('forgot')) ?>">પાસવર્ડ ભૂલી ગયા?</a>
    <a href="<?= e(url('register')) ?>">નવું એકાઉન્ટ</a>
</div>
<?php $this->end(); ?>
