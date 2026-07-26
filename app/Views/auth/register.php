<?php $this->extend('layouts/guest'); $this->set('title', 'Register'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">નવું એકાઉન્ટ બનાવો</h3>
<form method="post" action="<?= e(url('register')) ?>">
    <?= csrf_field() ?>
    <div class="form-group"><label>પૂરું નામ</label><input type="text" name="name" value="<?= e(old('name')) ?>" required></div>
    <div class="form-group"><label>ઈમેલ</label><input type="email" name="email" value="<?= e(old('email')) ?>" required></div>
    <div class="form-group"><label>મોબાઈલ (WhatsApp)</label><input type="text" name="mobile" value="<?= e(old('mobile')) ?>" placeholder="9876543210" required></div>
    <div class="form-group"><label>પાસવર્ડ</label><input type="password" name="password" required minlength="8"></div>
    <div class="form-group"><label>પાસવર્ડ ફરી</label><input type="password" name="password_confirmation" required minlength="8"></div>
    <button type="submit" class="btn btn-primary btn-block">રજિસ્ટર કરો</button>
</form>
<div class="text-center mt-3 small"><a href="<?= e(url('login')) ?>">પહેલેથી એકાઉન્ટ છે? લોગિન</a></div>
<?php $this->end(); ?>
