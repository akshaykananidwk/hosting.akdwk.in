<?php /* FILE: /app/Views/client/invoices/index.php — client invoices list */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Invoices'); ?>
<?php $this->section('content'); ?>
<?php
$statusBadge = static function (string $s): string {
    return match ($s) {
        'paid'      => 'badge-success',
        'unpaid'    => 'badge-warning',
        'overdue'   => 'badge-danger',
        'draft'     => 'badge-info',
        default     => 'badge-muted',
    };
};
?>

<div class="card">
    <h3 style="margin:0 0 12px">🧾 My Invoices</h3>
    <?php if (empty($invoices)): ?>
        <p class="muted">No invoices.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="<?= e(url('client/invoices/' . $inv['id'])) ?>"><strong><?= e($inv['invoice_number']) ?></strong></a></td>
                        <td><?= e($inv['issue_date'] ? date('d M Y', strtotime((string) $inv['issue_date'])) : '—') ?></td>
                        <td><?= e($inv['due_date'] ? date('d M Y', strtotime((string) $inv['due_date'])) : '—') ?></td>
                        <td><?= e(money($inv['total'])) ?></td>
                        <td><span class="badge <?= e($statusBadge($inv['status'])) ?>"><?= e($inv['status']) ?></span></td>
                        <td class="text-right">
                            <a class="btn btn-sm btn-outline" href="<?= e(url('client/invoices/' . $inv['id'])) ?>">View</a>
                            <?php if (in_array($inv['status'], ['unpaid', 'overdue'], true)): ?>
                                <a class="btn btn-sm btn-primary" href="<?= e(url('order/checkout/' . $inv['id'])) ?>">Pay Now</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
