<?php /* FILE: /app/Views/client/domains/index.php — client domains list */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Domains'); ?>
<?php $this->section('content'); ?>
<?php
$statusBadge = static function (string $s): string {
    return match ($s) {
        'active'      => 'badge-success',
        'pending'     => 'badge-warning',
        'expired'     => 'badge-danger',
        default       => 'badge-muted',
    };
};
?>

<div class="card">
    <h3 style="margin:0 0 12px">🌍 My Domains</h3>
    <?php if (empty($domains)): ?>
        <p class="muted">કોઈ domain registered નથી.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Auto Renew</th>
                        <th>Registered</th>
                        <th>Expiry</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($domains as $d): ?>
                    <tr>
                        <td><strong><?= e($d['domain']) ?></strong></td>
                        <td><?= e($d['type']) ?></td>
                        <td><span class="badge <?= e($statusBadge($d['status'])) ?>"><?= e($d['status']) ?></span></td>
                        <td><?= ((int) $d['auto_renew'] === 1) ? '✅' : '—' ?></td>
                        <td><?= e($d['reg_date'] ? date('d M Y', strtotime((string) $d['reg_date'])) : '—') ?></td>
                        <td><?= e($d['expiry_date'] ? date('d M Y', strtotime((string) $d['expiry_date'])) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
