<?php $this->extend('layouts/guest'); $this->set('title', 'Register'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">Create a new account</h3>
<form method="post" action="<?= e(url('register')) ?>">
    <?= csrf_field() ?>
    <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= e(old('name')) ?>" required></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e(old('email')) ?>" required></div>
    <div class="form-group"><label>Mobile (WhatsApp)</label><input type="text" name="mobile" value="<?= e(old('mobile')) ?>" placeholder="9876543210" required></div>
    <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="8"></div>
    <div class="form-group"><label>Confirm Password</label><input type="password" name="password_confirmation" required minlength="8"></div>
    <button type="submit" class="btn btn-primary btn-block">Register</button>
</form>
<div class="text-center mt-3 small"><a href="<?= e(url('login')) ?>">Already have an account? Sign in</a></div>
<?php $this->end(); ?>
