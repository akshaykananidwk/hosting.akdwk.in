<?php $this->extend('layouts/admin'); $this->set('title', 'Provisioning'); ?>
<?php
// FILE: /app/Views/admin/provisioning/index.php — queue monitor + retry
/** @var array $queue @var array $logs */
$qBadge = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
$logBadge = ['success' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'];
?>
<?php /* Content renders at top level for this View engine. */ ?>

<div class="card">
    <div class="card-head"><span>⚙️ Provisioning Queue</span></div>
    <div class="card-body">
        <?php if (empty($queue)): ?>
            <p class="muted">Queue ખાલી છે.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Domain</th><th>Action</th><th>Status</th><th>Attempts</th><th>Last Error</th><th>Created</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($queue as $q): ?>
                    <tr>
                        <td class="mono"><?= e($q['domain'] ?? '—') ?></td>
                        <td><?= e($q['action']) ?></td>
                        <td><span class="badge badge-<?= e($qBadge[$q['status']] ?? 'muted') ?>"><?= e($q['status']) ?></span></td>
                        <td class="small"><?= e((int) $q['attempts']) ?> / <?= e((int) $q['max_attempts']) ?></td>
                        <td class="small" style="max-width:260px"><?= e($q['last_error'] ?? '') ?></td>
                        <td class="small muted"><?= e($q['created_at']) ?></td>
                        <td>
                            <?php if ($q['status'] !== 'completed'): ?>
                            <form method="post" action="<?= e(url('admin/provisioning/' . (int) $q['id'] . '/retry')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline" data-confirm="આ job ફરી ચલાવવું છે?">Retry</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head"><span>📜 Recent Logs</span></div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="muted">કોઈ log નથી.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Domain</th><th>Step</th><th>Status</th><th>Message</th><th>સમય</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
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

