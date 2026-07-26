<?php /* FILE: /app/Views/client/database/index.php — database info + tools */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Database'); ?>
<?php $this->section('content'); ?>
<?php $sid = (int) $service['id']; ?>

<div class="card mb-3">
    <div class="flex items-center justify-between">
        <h3 style="margin:0">🗄️ Database — <?= e($service['domain']) ?></h3>
        <a class="btn btn-sm btn-outline" href="<?= e(url('client/services/' . $sid)) ?>">← Service</a>
    </div>
</div>

<div class="card mb-3">
    <?php if (empty($details) || empty($details['db_name'])): ?>
        <p class="muted">No database has been set up for this service.</p>
    <?php else: ?>
        <table class="table">
            <tbody>
                <tr><th>Database Name</th><td><code><?= e($details['db_name']) ?></code></td></tr>
                <tr><th>Database User</th><td><code><?= e($details['db_user'] ?? '—') ?></code></td></tr>
                <tr><th>Password</th><td><code><?= e($dbPass !== '' ? $dbPass : '—') ?></code></td></tr>
                <tr><th>Host</th><td><?= e($details['db_host'] ?? '127.0.0.1') ?></td></tr>
            </tbody>
        </table>
        <p class="small muted mt-2">⚠️ Keep these credentials confidential.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3 style="margin:0 0 10px">🔧 Manage Database</h3>
    <div class="flex gap-2" style="flex-wrap:wrap">
        <?php if (!empty($adminerUrl)): ?>
            <a class="btn btn-outline" href="<?= e($adminerUrl) ?>" target="_blank" rel="noopener">Open Adminer ↗</a>
        <?php else: ?>
            <button class="btn btn-outline" disabled>Adminer (configured none)</button>
        <?php endif; ?>
    </div>
    <p class="small muted mt-2">
        phpMyAdmin / Adminer Use the DB user and password above to sign in.
        Contact support for the phpMyAdmin URL, or use aaPanel's built-in
        phpMyAdmin (<code>/phpmyadmin</code>) Use it.
    </p>
</div>
<?php $this->end(); ?>
