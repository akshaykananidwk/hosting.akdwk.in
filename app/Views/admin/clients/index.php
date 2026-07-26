<?php // FILE: /app/Views/admin/clients/index.php — client list + search
$this->extend('layouts/admin'); $this->set('title', 'ગ્રાહકો (Clients)');
$badge = ['active' => 'success', 'inactive' => 'muted', 'suspended' => 'warning', 'closed' => 'danger'];
?>
<?php $this->section('content'); ?>

<div class="card">
    <div class="card-head">
        <span>ગ્રાહકો <span class="muted small">(કુલ <?= (int) ($meta['total'] ?? 0) ?>)</span></span>
        <a href="<?= e(url('admin/clients/create')) ?>" class="btn btn-primary btn-sm">+ નવો ગ્રાહક</a>
    </div>
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/clients')) ?>" class="flex gap-1 mb-3">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="ઈમેલ કે નામ થી શોધો…">
            <button class="btn btn-outline" type="submit">શોધો</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline" href="<?= e(url('admin/clients')) ?>">Clear</a><?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th><th>નામ</th><th>ઈમેલ</th><th>મોબાઈલ</th>
                        <th>Services</th><th>Credit</th><th>સ્થિતિ</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="8" class="text-center muted">કોઈ ગ્રાહક મળ્યો નહીં</td></tr>
                    <?php endif; ?>
                    <?php foreach ($clients as $c): ?>
                        <tr>
                            <td class="mono small"><?= e($c['client_code'] ?? '—') ?></td>
                            <td><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></td>
                            <td><?= e($c['email']) ?></td>
                            <td><?= e($c['phone'] ?? '—') ?></td>
                            <td><span class="badge badge-info"><?= (int) ($c['service_count'] ?? 0) ?></span></td>
                            <td><?= e(money($c['credit_balance'] ?? 0)) ?></td>
                            <td><span class="badge badge-<?= $badge[$c['status']] ?? 'muted' ?>"><?= e($c['status']) ?></span></td>
                            <td class="text-right"><a class="btn btn-outline btn-sm" href="<?= e(url('admin/clients/' . $c['id'])) ?>">જુઓ</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($meta['last_page'] ?? 1) > 1): $cur = (int) $meta['current_page']; $ex = ($q !== '' ? '&q=' . urlencode($q) : ''); ?>
        <div class="flex gap-1 mt-3 items-center justify-between">
            <span class="muted small">પાનું <?= $cur ?> / <?= (int) $meta['last_page'] ?></span>
            <span class="flex gap-1">
                <?php if ($cur > 1): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/clients') . '?page=' . ($cur - 1) . $ex) ?>">‹ પાછળ</a><?php endif; ?>
                <?php if ($cur < (int) $meta['last_page']): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/clients') . '?page=' . ($cur + 1) . $ex) ?>">આગળ ›</a><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $this->end(); ?>
