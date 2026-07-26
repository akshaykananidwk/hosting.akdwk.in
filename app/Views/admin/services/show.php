<?php $this->extend('layouts/admin'); $this->set('title', 'Service · ' . ($service['domain'] ?? '')); ?>
<?php
// FILE: /app/Views/admin/services/show.php — service detail + lifecycle
/** @var array $service @var ?array $client @var ?array $server @var ?array $details @var array $logs @var ?array $disk */
$statusBadge = [
    'active' => 'success', 'pending' => 'warning', 'suspended' => 'danger',
    'terminated' => 'muted', 'cancelled' => 'muted', 'fraud' => 'danger',
];
$logBadge = ['success' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'];
$sslBadge = ['active' => 'success', 'pending' => 'warning', 'expired' => 'danger', 'failed' => 'danger', 'none' => 'muted'];
$clientName = $client ? trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')) : '—';
$sid = (int) $service['id'];
?>
<?php /* Content renders at top level for this View engine. */ ?>

<div class="flex items-center justify-between mb-3">
    <div>
        <h3 class="mono" style="margin:0"><?= e($service['domain']) ?></h3>
        <span class="badge badge-<?= e($statusBadge[$service['status']] ?? 'muted') ?>"><?= e($service['status']) ?></span>
        <?php if (!empty($service['suspend_reason'])): ?>
            <span class="small muted">— <?= e($service['suspend_reason']) ?></span>
        <?php endif; ?>
    </div>
    <div class="flex gap-1">
        <?php if ($service['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('admin/services/' . $sid . '/suspend')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline" data-confirm="Suspend this service?">Suspend</button>
            </form>
        <?php elseif ($service['status'] === 'suspended'): ?>
            <form method="post" action="<?= e(url('admin/services/' . $sid . '/unsuspend')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-primary" data-confirm="Unsuspend Are you sure?">Unsuspend</button>
            </form>
        <?php endif; ?>
        <?php if (!in_array($service['status'], ['terminated'], true)): ?>
            <form method="post" action="<?= e(url('admin/services/' . $sid . '/terminate')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-danger" data-confirm="Terminate This is permanent — are you sure?">Terminate</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid cols-2">
    <div class="card">
        <div class="card-head"><span>ℹ️ Details</span></div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><th>Client</th><td><?= e($clientName) ?><?php if ($client): ?> <span class="small muted"><?= e($client['email']) ?></span><?php endif; ?></td></tr>
                    <tr><th>Server</th><td><?= e($server['name'] ?? '—') ?></td></tr>
                    <tr><th>aaPanel Site ID</th><td class="mono"><?= e($service['aapanel_site_id'] ?? '—') ?></td></tr>
                    <tr><th>Site Path</th><td class="mono small"><?= e($service['site_path'] ?? '—') ?></td></tr>
                    <tr><th>PHP</th><td><?= e($service['php_version']) ?></td></tr>
                    <tr><th>Billing</th><td><?= e($service['billing_cycle']) ?> · <?= e(money($service['recurring_amount'])) ?></td></tr>
                    <tr><th>Reg / Due</th><td><?= e($service['reg_date'] ?? '—') ?> → <?= e($service['next_due_date'] ?? '—') ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><span>🔐 Credentials & Usage</span></div>
        <div class="card-body">
            <?php if ($details): ?>
            <table class="table">
                <tbody>
                    <tr><th>DB Name</th><td class="mono"><?= e($details['db_name'] ?? '—') ?></td></tr>
                    <tr><th>DB User</th><td class="mono"><?= e($details['db_user'] ?? '—') ?></td></tr>
                    <tr><th>DB Pass</th><td class="mono"><?= e($dbPass ?? '—') ?></td></tr>
                    <tr><th>FTP Host</th><td class="mono"><?= e($details['ftp_host'] ?? '—') ?>:<?= e($details['ftp_port'] ?? 21) ?></td></tr>
                    <tr><th>FTP User</th><td class="mono"><?= e($details['ftp_user'] ?? '—') ?></td></tr>
                    <tr><th>FTP Pass</th><td class="mono"><?= e($ftpPass ?? '—') ?></td></tr>
                    <tr><th>SSL</th><td><span class="badge badge-<?= e($sslBadge[$details['ssl_status']] ?? 'muted') ?>"><?= e($details['ssl_status']) ?></span> <span class="small muted"><?= e($details['ssl_expires_at'] ?? '') ?></span></td></tr>
                </tbody>
            </table>
            <?php else: ?>
                <p class="muted">Provisioning pending — credentials have not been generated yet.</p>
            <?php endif; ?>

            <div class="mt-2">
                <div class="small muted">Disk (latest snapshot)</div>
                <?php $dTotal = $disk ? (float) ($disk['total_bytes'] ?? 0) : (float) $service['disk_used_mb'] * 1024 * 1024; ?>
                <div><?= e(format_bytes($dTotal)) ?> / <?= e(format_mb((int) $service['disk_limit_mb'])) ?>
                    <?php if ($disk): ?><span class="small muted">(<?= e(number_format((float) $disk['percent'], 1)) ?>%)</span><?php endif; ?>
                </div>
                <div class="small muted mt-1">Bandwidth (this month)</div>
                <div><?= e(format_bytes((float) $bwMonth)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><span>📜 Provisioning Logs</span></div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="muted">No logs.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Step</th><th>Status</th><th>Message</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['step']) ?></td>
                        <td><span class="badge badge-<?= e($logBadge[$log['status']] ?? 'muted') ?>"><?= e($log['status']) ?></span></td>
                        <td class="small"><?= e($log['message']) ?></td>
                        <td class="small muted"><?= e($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

