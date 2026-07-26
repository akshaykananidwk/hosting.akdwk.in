<?php /* FILE: /app/Views/client/profile/index.php — profile + security */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Profile'); ?>
<?php $this->section('content'); ?>
<?php
$twoFa = (int) ($user['two_factor_enabled'] ?? 0) === 1;
?>

<div class="grid cols-2">
    <div class="card">
        <h3 style="margin:0 0 12px">👤 My Profile</h3>
        <form method="post" action="<?= e(url('client/profile')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?= e(old('name', $user['name'] ?? '')) ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" required>
            </div>
            <div class="form-group">
                <label>Mobile</label>
                <input type="text" name="mobile" value="<?= e(old('mobile', $user['mobile'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>New Password <span class="small muted">(fill in only to change — minimum 8 characters)</span></label>
                <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin:0 0 12px">🔒 Security</h3>
        <div class="mb-2">
            <div class="label small muted">Two-Factor Authentication (2FA)</div>
            <div class="mt-1">
                <?php if ($twoFa): ?>
                    <span class="badge badge-success">Enabled</span>
                    <span class="small muted">— Your account is protected with 2FA.</span>
                <?php else: ?>
                    <span class="badge badge-muted">Disabled</span>
                    <span class="small muted">— Enabling 2FA is recommended for extra security.</span>
                <?php endif; ?>
            </div>
            <p class="small muted mt-2">
                2FA (TOTP / WhatsApp OTP) options will appear under login security.
                Open a support ticket if you need help.
            </p>
        </div>
        <?php if (!empty($client)): ?>
            <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
            <div class="small muted">Client Code</div>
            <div><strong><?= e($client['client_code'] ?? ('#' . ($client['id'] ?? ''))) ?></strong></div>
            <?php if (!empty($client['credit_balance'])): ?>
                <div class="small muted mt-2">Credit Balance</div>
                <div><strong><?= e(money($client['credit_balance'])) ?></strong></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php $this->end(); ?>
