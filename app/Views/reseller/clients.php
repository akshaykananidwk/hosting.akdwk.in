<?php /* FILE: /app/Views/reseller/clients.php — reseller's clients list */ ?>
<?php $this->extend('layouts/admin'); $this->set('title', __('clients')); ?>
<?php $this->section('content'); ?>

<?php
/** @var array<string,mixed> $clients  paginator: data,total,per_page,current_page,last_page */
$rows = $clients['data'] ?? [];
$page = (int) ($clients['current_page'] ?? 1);
$last = (int) ($clients['last_page'] ?? 1);
$total = (int) ($clients['total'] ?? 0);
?>

<div class="card">
    <div class="card-head">
        <span><?= e(__('clients')) ?></span>
        <span class="badge badge-muted"><?= $total ?></span>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= e(__('name')) ?></th>
                        <th><?= e(__('email')) ?></th>
                        <th><?= e(__('phone')) ?></th>
                        <th><?= e(__('status')) ?></th>
                        <th><?= e(__('created_at')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="muted"><?= e(__('no_records')) ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $c): ?>
                            <?php
                            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                            $badge = ($c['status'] ?? '') === 'active' ? 'badge-success'
                                : (($c['status'] ?? '') === 'suspended' ? 'badge-danger' : 'badge-muted');
                            ?>
                            <tr>
                                <td><?= (int) $c['id'] ?></td>
                                <td><?= e($name !== '' ? $name : ($c['company'] ?? '—')) ?></td>
                                <td><?= e($c['email'] ?? '') ?></td>
                                <td><?= e($c['phone'] ?? '—') ?></td>
                                <td><span class="badge <?= $badge ?>"><?= e(__($c['status'] ?? 'active')) ?></span></td>
                                <td class="muted"><?= e($c['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($last > 1): ?>
            <div class="flex items-center gap-2 mt-3">
                <?php if ($page > 1): ?>
                    <a class="btn btn-sm btn-outline" href="<?= e(url('reseller/clients?page=' . ($page - 1))) ?>">←</a>
                <?php endif; ?>
                <span class="muted"><?= $page ?> / <?= $last ?></span>
                <?php if ($page < $last): ?>
                    <a class="btn btn-sm btn-outline" href="<?= e(url('reseller/clients?page=' . ($page + 1))) ?>">→</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $this->end(); ?>
