<?php /* FILE: /app/Views/client/services/show.php — service detail + credentials */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Service — ' . ($service['domain'] ?? '')); ?>
<?php $this->section('content'); ?>
<?php
$sid = (int) $service['id'];
$statusBadge = match ($service['status']) {
    'active'    => 'badge-success',
    'pending'   => 'badge-warning',
    'suspended' => 'badge-danger',
    default     => 'badge-muted',
};
$usageBar = static function (int $used, int $limit, bool $unlimited = false): array {
    if ($unlimited || $limit <= 0) {
        return [0, ''];
    }
    $pct = (int) min(100, round($used / max(1, $limit) * 100));
    return [$pct, $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warn' : '')];
};
[$dPct, $dCls] = $usageBar((int) $service['disk_used_mb'], (int) $service['disk_limit_mb']);
[$bPct, $bCls] = $usageBar((int) $service['bandwidth_used_mb'], (int) $service['bandwidth_limit_mb'], (bool) $service['is_bw_unlimited']);
$sslStatus = $details['ssl_status'] ?? 'none';
?>

<div class="card mb-3">
    <div class="flex items-center justify-between">
        <div>
            <h2 style="margin:0"><?= e($service['domain']) ?></h2>
            <div class="small muted">PHP <?= e($service['php_version']) ?> · <?= e($service['billing_cycle']) ?> · <?= e($service['site_path'] ?: '—') ?></div>
        </div>
        <span class="badge <?= e($statusBadge) ?>"><?= e($service['status']) ?></span>
    </div>
</div>

<div class="grid cols-2 mb-3">
    <div class="card">
        <h3 style="margin:0 0 10px">📊 Disk Usage</h3>
        <div class="progress <?= e($dCls) ?>"><span style="width:<?= e((string) $dPct) ?>%"></span></div>
        <div class="small muted mt-1"><?= e(format_mb((int) $service['disk_used_mb'])) ?> / <?= e(format_mb((int) $service['disk_limit_mb'])) ?> (<?= e((string) $dPct) ?>%)</div>
    </div>
    <div class="card">
        <h3 style="margin:0 0 10px">📶 Bandwidth</h3>
        <?php if ((int) $service['is_bw_unlimited'] === 1): ?>
            <span class="badge badge-info">Unlimited</span>
        <?php else: ?>
            <div class="progress <?= e($bCls) ?>"><span style="width:<?= e((string) $bPct) ?>%"></span></div>
            <div class="small muted mt-1"><?= e(format_mb((int) $service['bandwidth_used_mb'])) ?> / <?= e(format_mb((int) $service['bandwidth_limit_mb'])) ?> (<?= e((string) $bPct) ?>%)</div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <h3 style="margin:0 0 10px">🔑 Access Credentials</h3>
    <div class="grid cols-2">
        <div>
            <h4 style="margin:0 0 6px">FTP / SSH</h4>
            <table class="table">
                <tbody>
                    <tr><th>Host</th><td><?= e($details['ftp_host'] ?? '—') ?></td></tr>
                    <tr><th>Port</th><td><?= e((string) ($details['ftp_port'] ?? 21)) ?></td></tr>
                    <tr><th>Username</th><td><?= e($details['ftp_user'] ?? $service['username'] ?? '—') ?></td></tr>
                    <tr><th>Password</th><td><code><?= e($ftpPass !== '' ? $ftpPass : '—') ?></code></td></tr>
                </tbody>
            </table>
        </div>
        <div>
            <h4 style="margin:0 0 6px">Database</h4>
            <table class="table">
                <tbody>
                    <tr><th>DB Name</th><td><?= e($details['db_name'] ?? '—') ?></td></tr>
                    <tr><th>DB User</th><td><?= e($details['db_user'] ?? '—') ?></td></tr>
                    <tr><th>DB Password</th><td><code><?= e($dbPass !== '' ? $dbPass : '—') ?></code></td></tr>
                    <tr><th>DB Host</th><td><?= e($details['db_host'] ?? '127.0.0.1') ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <p class="small muted mt-2">⚠️ These credentials are confidential — do not share them with anyone.</p>
</div>

<div class="card mb-3">
    <h3 style="margin:0 0 10px">⚡ Quick Actions</h3>
    <div class="flex gap-2" style="flex-wrap:wrap">
        <a class="btn btn-outline" href="<?= e(url('client/services/' . $sid . '/files')) ?>">📁 File Manager</a>
        <a class="btn btn-outline" href="<?= e(url('client/services/' . $sid . '/database')) ?>">🗄️ Database</a>
        <form method="post" action="<?= e(url('client/services/' . $sid . '/ssl')) ?>" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success" data-confirm="SSL install queue Add to?">🔒 Install SSL</button>
        </form>
    </div>
    <p class="small muted mt-2">
        SSL Status: <span class="badge <?= $sslStatus === 'active' ? 'badge-success' : ($sslStatus === 'pending' ? 'badge-warning' : 'badge-muted') ?>"><?= e($sslStatus) ?></span>
        <?php if (!empty($details['ssl_expires_at'])): ?> · Expires <?= e(date('d M Y', strtotime((string) $details['ssl_expires_at']))) ?><?php endif; ?>
    </p>
</div>

<div class="card">
    <h3 style="margin:0 0 10px">🔐 Change Site Password</h3>
    <form method="post" action="<?= e(url('client/services/' . $sid . '/password')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>New password (minimum 8 characters)</label>
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
</div>
<?php $this->end(); ?>
