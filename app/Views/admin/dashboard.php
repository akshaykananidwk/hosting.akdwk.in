<?php $this->extend('layouts/admin'); $this->set('title', 'Dashboard'); ?>
<?php
// FILE: /app/Views/admin/dashboard.php — admin overview tiles
/** @var array $servers @var array $recentLogs */
$serverBadge = ['online' => 'success', 'offline' => 'danger', 'maintenance' => 'warning', 'disabled' => 'muted'];
$logBadge = ['success' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'];
?>
<?php /* Content renders at top level: this View engine feeds top-level output into yield('content'). */ ?>

<div class="grid cols-4 mb-3">
    <div class="stat">
        <div class="label">કુલ Clients</div>
        <div class="value"><?= e(number_format((int) $totalClients)) ?></div>
    </div>
    <div class="stat">
        <div class="label">Active Services</div>
        <div class="value"><?= e(number_format((int) $activeServices)) ?></div>
    </div>
    <div class="stat">
        <div class="label">Pending Provisioning</div>
        <div class="value"><?= e(number_format((int) $pendingProvisioning)) ?></div>
    </div>
    <div class="stat">
        <div class="label">આજની આવક</div>
        <div class="value"><?= e(money($todayRevenue)) ?></div>
    </div>
</div>

<div class="grid cols-3 mb-3">
    <div class="stat">
        <div class="label">Unpaid Invoices</div>
        <div class="value sm"><?= e(number_format((int) $unpaidCount)) ?> · <?= e(money($unpaidSum)) ?></div>
    </div>
    <div class="stat">
        <div class="label">WhatsApp Queue (pending)</div>
        <div class="value"><?= e(number_format((int) $whatsappPending)) ?></div>
    </div>
    <div class="stat">
        <div class="label">Servers</div>
        <div class="value"><?= e(number_format(count($servers))) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <span>🖥️ Server Health</span>
        <a href="<?= e(url('admin/servers')) ?>" class="btn btn-sm btn-outline">બધા Servers</a>
    </div>
    <div class="card-body">
        <?php if (empty($servers)): ?>
            <p class="muted">કોઈ server ઉમેરાયો નથી.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Server</th><th>Status</th><th>RAM</th><th>Disk</th><th>Accounts</th></tr>
                </thead>
                <tbody>
                <?php foreach ($servers as $s): ?>
                    <?php
                    $ram = (float) ($s['ram_percent'] ?? 0);
                    $disk = (float) ($s['disk_percent'] ?? 0);
                    $ramCls = $ram >= 90 ? 'danger' : ($ram >= 75 ? 'warn' : '');
                    $diskCls = $disk >= 90 ? 'danger' : ($disk >= 75 ? 'warn' : '');
                    ?>
                    <tr>
                        <td><strong><?= e($s['name']) ?></strong></td>
                        <td><span class="badge badge-<?= e($serverBadge[$s['status']] ?? 'muted') ?>"><?= e($s['status']) ?></span></td>
                        <td style="min-width:120px">
                            <div class="progress <?= e($ramCls) ?>"><span style="width:<?= e(min(100, $ram)) ?>%"></span></div>
                            <span class="small muted"><?= e(number_format($ram, 1)) ?>%</span>
                        </td>
                        <td style="min-width:120px">
                            <div class="progress <?= e($diskCls) ?>"><span style="width:<?= e(min(100, $disk)) ?>%"></span></div>
                            <span class="small muted"><?= e(number_format($disk, 1)) ?>%</span>
                        </td>
                        <td><?= e((int) ($s['active_accounts'] ?? 0)) ?> / <?= e((int) ($s['max_accounts'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <span>⚙️ Recent Provisioning Activity</span>
        <a href="<?= e(url('admin/provisioning')) ?>" class="btn btn-sm btn-outline">Provisioning</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentLogs)): ?>
            <p class="muted">હજી કોઈ provisioning log નથી.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Domain</th><th>Step</th><th>Status</th><th>Message</th><th>સમય</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td class="mono"><?= e($log['domain'] ?? '—') ?></td>
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

