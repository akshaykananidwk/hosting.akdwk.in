<?php /* FILE: /app/Views/reseller/dashboard.php — reseller overview */ ?>
<?php $this->extend('layouts/admin'); $this->set('title', __('dashboard')); ?>
<?php $this->section('content'); ?>

<?php
/** @var array<string,mixed>|null $reseller */
/** @var int $clientsCount */
/** @var int $activeServices */
/** @var float $walletBalance */
/** @var array<int,array<string,mixed>> $recentClients */
$brand = $reseller['brand_name'] ?? ($reseller['company'] ?? '');
?>

<?php if (!$reseller): ?>
    <div class="alert alert-warning" data-auto="0">
        <?= e(__('status')) ?>: Reseller profile is not active yet. Please contact an administrator.
    </div>
<?php endif; ?>

<?php if ($brand !== ''): ?>
    <p class="muted mb-3"><?= e($brand) ?></p>
<?php endif; ?>

<div class="grid cols-3">
    <div class="stat">
        <div class="label"><?= e(__('clients')) ?></div>
        <div class="value"><?= (int) $clientsCount ?></div>
    </div>
    <div class="stat">
        <div class="label"><?= e(__('services')) ?> — <?= e(__('active')) ?></div>
        <div class="value"><?= (int) $activeServices ?></div>
    </div>
    <div class="stat">
        <div class="label"><?= e(__('wallet_balance')) ?></div>
        <div class="value"><?= e(money($walletBalance)) ?></div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-head">
        <span><?= e(__('clients')) ?></span>
        <a href="<?= e(url('reseller/clients')) ?>" class="btn btn-sm btn-outline"><?= e(__('clients')) ?> →</a>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= e(__('name')) ?></th>
                        <th><?= e(__('email')) ?></th>
                        <th><?= e(__('status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentClients)): ?>
                        <tr><td colspan="4" class="muted"><?= e(__('no_records')) ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($recentClients as $c): ?>
                            <?php
                            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                            $badge = ($c['status'] ?? '') === 'active' ? 'badge-success'
                                : (($c['status'] ?? '') === 'suspended' ? 'badge-danger' : 'badge-muted');
                            ?>
                            <tr>
                                <td><?= (int) $c['id'] ?></td>
                                <td><?= e($name !== '' ? $name : ($c['company'] ?? '—')) ?></td>
                                <td><?= e($c['email'] ?? '') ?></td>
                                <td><span class="badge <?= $badge ?>"><?= e(__($c['status'] ?? 'active')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->end(); ?>
