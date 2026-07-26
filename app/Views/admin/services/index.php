<?php $this->extend('layouts/admin'); $this->set('title', 'Services'); ?>
<?php
// FILE: /app/Views/admin/services/index.php — hosting services list
/** @var array $page */
$rows = $page['data'] ?? [];
$current = (int) ($page['current_page'] ?? 1);
$last = (int) ($page['last_page'] ?? 1);
$statusBadge = [
    'active' => 'success', 'pending' => 'warning', 'suspended' => 'danger',
    'terminated' => 'muted', 'cancelled' => 'muted', 'fraud' => 'danger',
];
$bar = function (int $used, int $limit): array {
    if ($limit <= 0) {
        return [0.0, ''];
    }
    $pct = min(100.0, $used / $limit * 100);
    $cls = $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warn' : '');
    return [$pct, $cls];
};
?>
<?php /* Content renders at top level for this View engine. */ ?>

<div class="card">
    <div class="card-head">
        <span>🌐 Services</span>
        <span class="small muted"><?= e(number_format((int) ($page['total'] ?? 0))) ?> total</span>
    </div>
    <div class="card-body">
        <?php if (empty($rows)): ?>
            <p class="muted">No services.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Domain</th><th>Client</th><th>Status</th><th>Next Due</th><th>Disk</th><th>Bandwidth</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $clientName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                    if ($clientName === '') {
                        $clientName = (string) ($r['company'] ?? '—');
                    }
                    [$dPct, $dCls] = $bar((int) $r['disk_used_mb'], (int) $r['disk_limit_mb']);
                    $bwUnlimited = (int) ($r['is_bw_unlimited'] ?? 0) === 1;
                    [$bPct, $bCls] = $bar((int) $r['bandwidth_used_mb'], (int) $r['bandwidth_limit_mb']);
                    ?>
                    <tr>
                        <td class="mono"><?= e($r['domain']) ?></td>
                        <td><?= e($clientName) ?></td>
                        <td><span class="badge badge-<?= e($statusBadge[$r['status']] ?? 'muted') ?>"><?= e($r['status']) ?></span></td>
                        <td class="small"><?= e($r['next_due_date'] ?? '—') ?></td>
                        <td style="min-width:130px">
                            <div class="progress <?= e($dCls) ?>"><span style="width:<?= e($dPct) ?>%"></span></div>
                            <span class="small muted"><?= e(format_mb((int) $r['disk_used_mb'])) ?> / <?= e(format_mb((int) $r['disk_limit_mb'])) ?></span>
                        </td>
                        <td style="min-width:130px">
                            <?php if ($bwUnlimited): ?>
                                <span class="badge badge-info">Unlimited</span>
                            <?php else: ?>
                                <div class="progress <?= e($bCls) ?>"><span style="width:<?= e($bPct) ?>%"></span></div>
                                <span class="small muted"><?= e(format_mb((int) $r['bandwidth_used_mb'])) ?> / <?= e(format_mb((int) $r['bandwidth_limit_mb'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?= e(url('admin/services/' . (int) $r['id'])) ?>" class="btn btn-sm btn-outline">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($last > 1): ?>
        <div class="flex items-center justify-between mt-2">
            <span class="small muted">Page <?= e($current) ?> / <?= e($last) ?></span>
            <div class="flex gap-1">
                <?php if ($current > 1): ?>
                    <a class="btn btn-sm btn-outline" href="<?= e(url('admin/services?page=' . ($current - 1))) ?>">← Previous</a>
                <?php endif; ?>
                <?php if ($current < $last): ?>
                    <a class="btn btn-sm btn-outline" href="<?= e(url('admin/services?page=' . ($current + 1))) ?>">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

