<?php // FILE: /app/Views/admin/products/index.php — products grouped by group
$this->extend('layouts/admin'); $this->set('title', 'Products (પ્રોડક્ટ)');
$pBadge = ['active' => 'success', 'hidden' => 'muted', 'retired' => 'danger'];
?>
<?php $this->section('content'); ?>

<div class="flex justify-between items-center mb-3">
    <h3 style="margin:0">Products / Plans</h3>
    <a href="<?= e(url('admin/products/create')) ?>" class="btn btn-primary btn-sm">+ નવી પ્રોડક્ટ</a>
</div>

<?php if (empty($groups)): ?>
    <div class="alert alert-info">કોઈ product group નથી.</div>
<?php endif; ?>

<?php foreach ($groups as $g): $rows = $byGroup[(int) $g['id']] ?? []; ?>
    <div class="card">
        <div class="card-head">
            <span><?= e($g['name']) ?> <span class="badge badge-muted"><?= e($g['type']) ?></span></span>
            <span class="muted small"><?= count($rows) ?> plans</span>
        </div>
        <div class="card-body table-wrap">
            <table class="table">
                <thead><tr><th>Plan</th><th>Disk</th><th>Bandwidth</th><th>Sites</th><th>DB</th><th>PHP</th><th>SSL</th><th>Backup</th><th>Monthly</th><th>Yearly</th><th>સ્થિતિ</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($rows)): ?><tr><td colspan="12" class="text-center muted">આ group માં કોઈ plan નથી</td></tr><?php endif; ?>
                    <?php foreach ($rows as $p): ?>
                        <tr>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td class="small"><?= e(format_mb((int) $p['disk_mb'])) ?></td>
                            <td class="small"><?= ((int) $p['is_bw_unlimited'] === 1) ? 'Unlimited' : e(format_mb((int) $p['bandwidth_mb'])) ?></td>
                            <td><?= (int) $p['max_sites'] ?></td>
                            <td><?= (int) $p['max_databases'] ?></td>
                            <td class="small">PHP <?= e($p['php_version']) ?></td>
                            <td><?= ((int) $p['free_ssl'] === 1) ? '<span class="badge badge-success">Free</span>' : '<span class="badge badge-muted">—</span>' ?></td>
                            <td class="small"><?= e($p['backup_freq']) ?></td>
                            <td><?= $p['monthly'] !== null ? e(money($p['monthly'])) : '—' ?></td>
                            <td><?= $p['yearly'] !== null ? e(money($p['yearly'])) : '—' ?></td>
                            <td><span class="badge badge-<?= $pBadge[$p['status']] ?? 'muted' ?>"><?= e($p['status']) ?></span></td>
                            <td class="text-right"><a class="btn btn-outline btn-sm" href="<?= e(url('admin/products/' . $p['id'] . '/edit')) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php $this->end(); ?>
