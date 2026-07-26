<?php // FILE: /app/Views/admin/transactions/index.php — transaction ledger
$this->extend('layouts/admin'); $this->set('title', 'Transactions (Transactions)');
$tBadge = ['success' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'refunded' => 'info'];
$statuses = ['' => 'All', 'pending' => 'Pending', 'success' => 'Success', 'failed' => 'Failed', 'refunded' => 'Refunded'];
$manual = ['manual', 'upi_manual', 'bank_transfer'];
?>

<div class="card">
    <div class="card-head"><span>Transactions <span class="muted small">(Total <?= (int) ($meta['total'] ?? 0) ?>)</span></span></div>
    <div class="card-body">
        <div class="flex gap-1 mb-3" style="flex-wrap:wrap">
            <?php foreach ($statuses as $val => $lbl): ?>
                <a class="btn btn-sm <?= $status === $val ? 'btn-primary' : 'btn-outline' ?>"
                   href="<?= e(url('admin/transactions') . ($val !== '' ? '?status=' . $val : '')) ?>"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Client</th><th>Invoice</th><th>Gateway</th><th>Amount</th><th>UTR</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($transactions)): ?><tr><td colspan="9" class="text-center muted">No transactions found</td></tr><?php endif; ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= (int) $t['id'] ?></td>
                            <td><?= e(trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: '—') ?></td>
                            <td class="mono small">
                                <?php if (!empty($t['invoice_id'])): ?><a href="<?= e(url('admin/invoices/' . $t['invoice_id'])) ?>"><?= e($t['invoice_number'] ?? ('#' . $t['invoice_id'])) ?></a><?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?= e($t['gateway']) ?></td>
                            <td><?= e(money($t['amount'])) ?></td>
                            <td class="mono small"><?= e($t['utr'] ?: '—') ?></td>
                            <td><span class="badge badge-<?= $tBadge[$t['status']] ?? 'muted' ?>"><?= e($t['status']) ?></span></td>
                            <td class="small"><?= e($t['created_at']) ?></td>
                            <td class="text-right">
                                <?php if ($t['status'] === 'pending' && in_array($t['gateway'], $manual, true)): ?>
                                    <form method="post" action="<?= e(url('admin/transactions/' . $t['id'] . '/approve')) ?>" data-confirm="Approve Are you sure?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($meta['last_page'] ?? 1) > 1): $cur = (int) $meta['current_page']; $ex = ($status !== '' ? '&status=' . urlencode($status) : ''); ?>
        <div class="flex gap-1 mt-3 items-center justify-between">
            <span class="muted small">Page <?= $cur ?> / <?= (int) $meta['last_page'] ?></span>
            <span class="flex gap-1">
                <?php if ($cur > 1): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/transactions') . '?page=' . ($cur - 1) . $ex) ?>">‹ Previous</a><?php endif; ?>
                <?php if ($cur < (int) $meta['last_page']): ?><a class="btn btn-outline btn-sm" href="<?= e(url('admin/transactions') . '?page=' . ($cur + 1) . $ex) ?>">Next ›</a><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

