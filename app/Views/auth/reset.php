<?php $this->extend('layouts/guest'); $this->set('title', 'Reset Password'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">New Password</h3>
<form method="post" action="<?= e(url('reset')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <div class="form-group"><label>New Password</label><input type="password" name="password" required minlength="8"></div>
    <div class="form-group"><label>Re-enter</label><input type="password" name="password_confirmation" required minlength="8"></div>
    <button type="submit" class="btn btn-primary btn-block">Change Password</button>
</form>
<?php $this->end(); ?>
