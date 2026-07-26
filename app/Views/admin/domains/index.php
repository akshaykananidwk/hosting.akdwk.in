<?php // FILE: /app/Views/admin/domains/index.php — domain list
$this->extend('layouts/admin'); $this->set('title', 'Domains (ડોમેન)');
$dBadge = ['active' => 'success', 'pending' => 'warning', 'expired' => 'danger', 'cancelled' => 'muted', 'transferred' => 'info'];
$statuses = ['' => 'બધા', 'active' => 'Active', 'pending' => 'Pending', 'expired' => 'Expired', 'cancelled' => 'Cancelled'];
$today = date('Y-m-d');
?>

<div class="card">
    <div class="card-head"><span>Domains <span class="muted small">(કુલ <?= (int) ($meta['total'] ?? 0) ?>)</span></span></div>
    <div class="card-body">
        <div class="flex gap-1 mb-3" style="flex-wrap:wrap">
            <?php foreach ($statuses as $val => $lbl): ?>
                <a class="btn btn-sm <?= $status === $val ? 'btn-primary' : 'btn-outline' ?>"
                   href="<?= e(url('admin/domains') . ($val !== '' ? '?status=' . $val : '')) ?>"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Domain</th><th>ગ્રાહક</th><th>Type</th><th>Registrar</th><th>Auto Renew</th><th>Expiry</th><th>સ્થિતિ</th></tr></thead>
                <tbody>
                    <?php if (empty($domains)): ?><tr><td colspan="7" class="text-center muted">કોઈ domain મળ્યું નહીં</td></tr><?php endif; ?>
                    <?php foreach ($domains as $d): $expSoon = !empty($d['expiry_date']) && $d['expiry_date'] >= $today && $d['expiry_date'] <= date('Y-m-d', strtotime('+30 days')); ?>
                        <tr>
                            <td><strong><?= e($d['domain']) ?></strong></td>
                            <td><?= e(trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?: ($d['company'] ?? '—')) ?></td>
                            <td class="small"><?= e($d['type']) ?></td>
                            <td class="small"><?= e($d['registrar'] ?? '—') ?></td>
                            <td><?= ((int) $d['auto_renew'] === 1) ? '<span class="badge badge-success">On</span>' : '<span class="badge badge-muted">Off</span>' ?></td>
                            <td class="small"><?= e($d['expiry_date'] ?? '—') ?> <?= $expSoon ? '<span class="badge badge-warning">soon</span>' : '' ?></td>
                            <td><span class="badge badge-<?= $dBadge[$d['status']] ?? 'muted' ?>"><?= e($d['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($meta['last_page'] ?? 1) > 1): $cur = (int) $meta['current_page']; $ex = ($status !== '' ? '&status=' . urlencode($status) : ''); ?>
        <div class="flex gap-1 mt-3 items-center justify-between">
            <span class="muted small">પાનું <?= $cur ?> / <?= (int) $meta['last_page'] ?></span>
            <span class="flex gap-1">
                <?php if ($cur > 1): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/domains') . '?page=' . ($cur - 1) . $ex) ?>">‹ પાછળ</a><?php endif; ?>
                <?php if ($cur < (int) $meta['last_page']): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/domains') . '?page=' . ($cur + 1) . $ex) ?>">આગળ ›</a><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

