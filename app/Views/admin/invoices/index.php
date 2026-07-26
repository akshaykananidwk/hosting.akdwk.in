<?php // FILE: /app/Views/admin/invoices/index.php — invoice list + status filter
$this->extend('layouts/admin'); $this->set('title', 'Invoices (Invoices)');
$iBadge = ['paid' => 'success', 'unpaid' => 'warning', 'overdue' => 'danger', 'draft' => 'muted', 'cancelled' => 'muted', 'refunded' => 'info'];
$statuses = ['' => 'All', 'unpaid' => 'Unpaid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'];
?>

<div class="card">
    <div class="card-head">
        <span>Invoices <span class="muted small">(Total <?= (int) ($meta['total'] ?? 0) ?>)</span></span>
    </div>
    <div class="card-body">
        <div class="flex gap-1 mb-3" style="flex-wrap:wrap">
            <?php foreach ($statuses as $val => $lbl): ?>
                <a class="btn btn-sm <?= $status === $val ? 'btn-primary' : 'btn-outline' ?>"
                   href="<?= e(url('admin/invoices') . ($val !== '' ? '?status=' . $val : '')) ?>"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Invoice</th><th>Client</th><th>Issue</th><th>Due</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($invoices)): ?><tr><td colspan="7" class="text-center muted">No invoices found</td></tr><?php endif; ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td class="mono"><?= e($inv['invoice_number']) ?></td>
                            <td><?= e(trim(($inv['first_name'] ?? '') . ' ' . ($inv['last_name'] ?? '')) ?: ($inv['company'] ?? '—')) ?></td>
                            <td class="small"><?= e($inv['issue_date']) ?></td>
                            <td class="small"><?= e($inv['due_date']) ?></td>
                            <td><?= e(money($inv['total'])) ?></td>
                            <td><span class="badge badge-<?= $iBadge[$inv['status']] ?? 'muted' ?>"><?= e($inv['status']) ?></span></td>
                            <td class="text-right"><a class="btn btn-outline btn-sm" href="<?= e(url('admin/invoices/' . $inv['id'])) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($meta['last_page'] ?? 1) > 1): $cur = (int) $meta['current_page']; $ex = ($status !== '' ? '&status=' . urlencode($status) : ''); ?>
        <div class="flex gap-1 mt-3 items-center justify-between">
            <span class="muted small">Page <?= $cur ?> / <?= (int) $meta['last_page'] ?></span>
            <span class="flex gap-1">
                <?php if ($cur > 1): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/invoices') . '?page=' . ($cur - 1) . $ex) ?>">‹ Previous</a><?php endif; ?>
                <?php if ($cur < (int) $meta['last_page']): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/invoices') . '?page=' . ($cur + 1) . $ex) ?>">Next ›</a><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

