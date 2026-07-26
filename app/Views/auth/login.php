<?php $this->extend('layouts/guest'); $this->set('title', 'Login'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">Sign In</h3>
<form method="post" action="<?= e(url('login')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
</form>
<div class="flex justify-between mt-3 small">
    <a href="<?= e(url('forgot')) ?>">Forgot password?</a>
    <a href="<?= e(url('register')) ?>">Create account</a>
</div>
<?php $this->end(); ?>
