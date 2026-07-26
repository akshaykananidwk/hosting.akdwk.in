<?php $this->extend('layouts/admin'); $this->set('title', 'Logs'); ?>
<?php
// FILE: /app/Views/admin/logs/index.php — unified log viewer (tabbed)
/** @var string $tab @var array $tabs @var array $rows */
$labels = ['aapanel' => 'aaPanel', 'cron' => 'Cron', 'login' => 'Login', 'audit' => 'Audit'];
$statusBadge = [
    'success' => 'success', 'failed' => 'danger', 'error' => 'danger', 'skipped' => 'muted',
    'locked' => 'danger', '2fa_pending' => 'warning',
];
$badge = fn(string $s): string => 'badge-' . ($statusBadge[$s] ?? 'muted');
?>
<?php /* Content renders at top level for this View engine. */ ?>

<div class="card">
    <div class="card-head"><span>📜 System Logs</span></div>
    <div class="card-body">
        <div class="flex gap-1 mb-3">
            <?php foreach ($tabs as $t): ?>
                <a href="<?= e(url('admin/logs?tab=' . $t)) ?>"
                   class="btn btn-sm <?= $t === $tab ? 'btn-primary' : 'btn-outline' ?>">
                    <?= e($labels[$t] ?? $t) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($rows)): ?>
            <p class="muted">આ tab માં કોઈ log નથી.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <?php if ($tab === 'aapanel'): ?>
                    <thead><tr><th>Server</th><th>Endpoint</th><th>Method</th><th>HTTP</th><th>Status</th><th>ms</th><th>સમય</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="small"><?= e($r['server_id'] ?? '—') ?></td>
                            <td class="small mono"><?= e($r['endpoint']) ?></td>
                            <td class="small"><?= e($r['method'] ?? '') ?></td>
                            <td class="small"><?= e($r['http_code'] ?? '') ?></td>
                            <td><span class="badge <?= e($badge((string) $r['status'])) ?>"><?= e($r['status']) ?></span></td>
                            <td class="small"><?= e($r['duration_ms'] ?? '') ?></td>
                            <td class="small muted"><?= e($r['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php elseif ($tab === 'cron'): ?>
                    <thead><tr><th>Slug</th><th>Status</th><th>ms</th><th>Output</th><th>સમય</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="small mono"><?= e($r['slug'] ?? '—') ?></td>
                            <td><span class="badge <?= e($badge((string) $r['status'])) ?>"><?= e($r['status']) ?></span></td>
                            <td class="small"><?= e($r['duration_ms'] ?? '') ?></td>
                            <td class="small" style="max-width:360px"><?= e(mb_substr((string) ($r['output'] ?? ''), 0, 200)) ?></td>
                            <td class="small muted"><?= e($r['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php elseif ($tab === 'login'): ?>
                    <thead><tr><th>Email</th><th>IP</th><th>Status</th><th>Reason</th><th>સમય</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="small"><?= e($r['email'] ?? '—') ?></td>
                            <td class="small mono"><?= e($r['ip_address'] ?? '') ?></td>
                            <td><span class="badge <?= e($badge((string) $r['status'])) ?>"><?= e($r['status']) ?></span></td>
                            <td class="small"><?= e($r['reason'] ?? '') ?></td>
                            <td class="small muted"><?= e($r['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php else: /* audit */ ?>
                    <thead><tr><th>User</th><th>Action</th><th>Model</th><th>IP</th><th>સમય</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="small"><?= e($r['user_id'] ?? '—') ?></td>
                            <td class="small"><?= e($r['action']) ?></td>
                            <td class="small"><?= e($r['model_type'] ?? '') ?><?= isset($r['model_id']) && $r['model_id'] !== null ? ' #' . e($r['model_id']) : '' ?></td>
                            <td class="small mono"><?= e($r['ip_address'] ?? '') ?></td>
                            <td class="small muted"><?= e($r['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

