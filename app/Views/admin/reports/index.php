<?php // FILE: /app/Views/admin/reports/index.php — revenue, MRR, plan-wise sales
$this->extend('layouts/admin'); $this->set('title', 'Reports (Reports)');
?>

<div class="flex justify-between items-center mb-3">
    <h3 style="margin:0">Business Report</h3>
    <a href="<?= e(url('admin/reports/gst')) ?>" class="btn btn-outline btn-sm">⬇ GST CSV (this month)</a>
</div>

<div class="grid cols-4 mb-3">
    <div class="stat"><div class="label">Revenue (this month)</div><div class="value"><?= e(money($revenueMonth)) ?></div></div>
    <div class="stat"><div class="label">Revenue (this year)</div><div class="value"><?= e(money($revenueYear)) ?></div></div>
    <div class="stat"><div class="label">MRR (est.)</div><div class="value"><?= e(money($mrr)) ?></div></div>
    <div class="stat"><div class="label">Unpaid Dues</div><div class="value sm"><?= e(money($stats['unpaid_amount'] ?? 0)) ?></div></div>
</div>

<div class="grid cols-4 mb-3">
    <div class="stat"><div class="label">Clients</div><div class="value sm"><?= (int) ($stats['clients'] ?? 0) ?></div></div>
    <div class="stat"><div class="label">Active Services</div><div class="value sm"><?= (int) ($stats['active_services'] ?? 0) ?></div></div>
    <div class="stat"><div class="label">Unpaid Invoices</div><div class="value sm"><?= (int) ($stats['unpaid_invoices'] ?? 0) ?></div></div>
    <div class="stat"><div class="label">Plans Sold</div><div class="value sm"><?= count($plans) ?></div></div>
</div>

<div class="card">
    <div class="card-head">Plan-wise Sales (active services)</div>
    <div class="card-body">
        <?php if (empty($plans)): ?>
            <p class="muted text-center">No active services.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Plan</th><th>Count</th><th>Recurring Revenue</th><th style="width:40%">Share</th></tr></thead>
                    <tbody>
                        <?php foreach ($plans as $p): $pct = $planMax > 0 ? round((float) $p['revenue'] / $planMax * 100) : 0; ?>
                            <tr>
                                <td><strong><?= e($p['name']) ?></strong></td>
                                <td><span class="badge badge-info"><?= (int) $p['count'] ?></span></td>
                                <td><?= e(money($p['revenue'])) ?></td>
                                <td>
                                    <div class="progress" title="<?= $pct ?>%"><span style="width:<?= $pct ?>%"></span></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<p class="muted small">MRR = active services is the sum of recurring_amount (approximate). Revenue = successful transactions.</p>

