<?php /* FILE: /app/Views/client/services/index.php — client services list */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'My Services'); ?>
<?php $this->section('content'); ?>
<?php
$statusBadge = static function (string $s): string {
    return match ($s) {
        'active'                 => 'badge-success',
        'pending'                => 'badge-warning',
        'suspended'              => 'badge-danger',
        'terminated', 'cancelled', 'fraud' => 'badge-muted',
        default                  => 'badge-info',
    };
};
$usageBar = static function (int $used, int $limit, bool $unlimited = false): array {
    if ($unlimited || $limit <= 0) {
        return [0, '', 'Unlimited'];
    }
    $pct = (int) min(100, round($used / max(1, $limit) * 100));
    $cls = $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warn' : '');
    return [$pct, $cls, $pct . '%'];
};
?>

<div class="card">
    <div class="flex items-center justify-between mb-2">
        <h3 style="margin:0">🌐 My Services</h3>
        <a class="btn btn-sm btn-primary" href="<?= e(url('store')) ?>">+ New Order</a>
    </div>

    <?php if (empty($services)): ?>
        <p class="muted">No services yet. <a href="<?= e(url('store')) ?>">Store Get hosting from →</a></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Disk</th>
                        <th>Bandwidth</th>
                        <th>Next Due</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <?php
                    [$dPct, $dCls] = $usageBar((int) $s['disk_used_mb'], (int) $s['disk_limit_mb']);
                    [$bPct, $bCls] = $usageBar((int) $s['bandwidth_used_mb'], (int) $s['bandwidth_limit_mb'], (bool) $s['is_bw_unlimited']);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($s['domain']) ?></strong>
                            <div class="small muted">PHP <?= e($s['php_version']) ?></div>
                        </td>
                        <td><span class="badge <?= e($statusBadge($s['status'])) ?>"><?= e($s['status']) ?></span></td>
                        <td style="min-width:150px">
                            <div class="progress <?= e($dCls) ?>"><span style="width:<?= e((string) $dPct) ?>%"></span></div>
                            <div class="small muted"><?= e(format_mb((int) $s['disk_used_mb'])) ?> / <?= e(format_mb((int) $s['disk_limit_mb'])) ?></div>
                        </td>
                        <td style="min-width:150px">
                            <?php if ((int) $s['is_bw_unlimited'] === 1): ?>
                                <span class="badge badge-info">Unlimited</span>
                            <?php else: ?>
                                <div class="progress <?= e($bCls) ?>"><span style="width:<?= e((string) $bPct) ?>%"></span></div>
                                <div class="small muted"><?= e(format_mb((int) $s['bandwidth_used_mb'])) ?> / <?= e(format_mb((int) $s['bandwidth_limit_mb'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($s['next_due_date'] ? date('d M Y', strtotime((string) $s['next_due_date'])) : '—') ?></td>
                        <td class="text-right"><a class="btn btn-sm btn-outline" href="<?= e(url('client/services/' . $s['id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
