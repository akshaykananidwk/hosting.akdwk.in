<?php /* FILE: /app/Views/client/dashboard.php — client dashboard */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Dashboard'); ?>
<?php $this->section('content'); ?>
<?php
$u = current_user() ?? ['name' => 'Client'];
$statusBadge = static function (string $s): string {
    return match ($s) {
        'active'    => 'badge-success',
        'pending'   => 'badge-warning',
        'suspended' => 'badge-danger',
        default     => 'badge-muted',
    };
};
$usageBar = static function (int $used, int $limit, bool $unlimited = false): array {
    if ($unlimited || $limit <= 0) {
        return [0, '', 'Unlimited'];
    }
    $pct = (int) min(100, round($used / max(1, $limit) * 100));
    $cls = $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warn' : '');
    return [$pct, $cls, $pct . '%'];
};
?>

<div class="card mb-3">
    <h2 style="margin:0 0 4px">Welcome, <?= e($u['name'] ?? 'Client') ?> 👋</h2>
    <p class="muted" style="margin:0">Here is a quick overview of your hosting account.</p>
</div>

<div class="grid cols-3 mb-3">
    <div class="stat">
        <div class="label">Active Services</div>
        <div class="value"><?= e((string) $activeCount) ?></div>
        <a class="small" href="<?= e(url('client/services')) ?>">View all services →</a>
    </div>
    <div class="stat">
        <div class="label">Unpaid Invoices</div>
        <div class="value"><?= e((string) $unpaidCount) ?></div>
        <div class="small muted"><?= e(money($unpaidSum)) ?> Outstanding</div>
    </div>
    <div class="stat">
        <div class="label">Open Tickets</div>
        <div class="value"><?= e((string) $openTickets) ?></div>
        <a class="small" href="<?= e(url('client/tickets')) ?>">Support →</a>
    </div>
</div>

<div class="card mb-3">
    <div class="flex items-center justify-between mb-2">
        <h3 style="margin:0">🌐 My Services</h3>
        <a class="btn btn-sm btn-outline" href="<?= e(url('client/services')) ?>">View all</a>
    </div>
    <?php if (empty($services)): ?>
        <p class="muted">No services yet. <a href="<?= e(url('store')) ?>">Order new hosting →</a></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Disk</th>
                        <th>Bandwidth</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <?php
                    [$dPct, $dCls, $dLabel] = $usageBar((int) $s['disk_used_mb'], (int) $s['disk_limit_mb']);
                    [$bPct, $bCls, $bLabel] = $usageBar((int) $s['bandwidth_used_mb'], (int) $s['bandwidth_limit_mb'], (bool) $s['is_bw_unlimited']);
                    ?>
                    <tr>
                        <td><strong><?= e($s['domain']) ?></strong></td>
                        <td><span class="badge <?= e($statusBadge($s['status'])) ?>"><?= e($s['status']) ?></span></td>
                        <td style="min-width:150px">
                            <div class="progress <?= e($dCls) ?>"><span style="width:<?= e((string) $dPct) ?>%"></span></div>
                            <div class="small muted"><?= e(format_mb((int) $s['disk_used_mb'])) ?> / <?= e(format_mb((int) $s['disk_limit_mb'])) ?></div>
                        </td>
                        <td style="min-width:150px">
                            <?php if ((int) $s['is_bw_unlimited'] === 1): ?>
                                <span class="badge badge-info">Unlimited</span>
                            <?php else: ?>
                                <div class="progress <?= e($bCls) ?>"><span style="width:<?= e((string) $bPct) ?>%"></span></div>
                                <div class="small muted"><?= e(format_mb((int) $s['bandwidth_used_mb'])) ?> / <?= e(format_mb((int) $s['bandwidth_limit_mb'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><a class="btn btn-sm btn-outline" href="<?= e(url('client/services/' . $s['id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="grid cols-2">
    <div class="card">
        <h3 style="margin:0 0 10px">🧾 Recent Invoices</h3>
        <?php if (empty($recentInvoices)): ?>
            <p class="muted">No invoices.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>#</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentInvoices as $inv): ?>
                        <?php
                        $ic = match ($inv['status']) {
                            'paid' => 'badge-success', 'unpaid' => 'badge-warning',
                            'overdue' => 'badge-danger', 'draft' => 'badge-info',
                            default => 'badge-muted',
                        };
                        ?>
                        <tr>
                            <td><a href="<?= e(url('client/invoices/' . $inv['id'])) ?>"><?= e($inv['invoice_number']) ?></a></td>
                            <td><?= e(money($inv['total'])) ?></td>
                            <td><span class="badge <?= e($ic) ?>"><?= e($inv['status']) ?></span></td>
                            <td class="text-right">
                                <?php if (in_array($inv['status'], ['unpaid', 'overdue'], true)): ?>
                                    <a class="btn btn-sm btn-primary" href="<?= e(url('order/checkout/' . $inv['id'])) ?>">Pay</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin:0 0 10px">📢 Announcements</h3>
        <?php if (empty($announcements)): ?>
            <p class="muted">No announcements.</p>
        <?php else: ?>
            <?php foreach ($announcements as $a): ?>
                <div class="mb-2" style="border-bottom:1px solid var(--border);padding-bottom:10px">
                    <strong><?= e($a['title']) ?></strong>
                    <div class="small muted"><?= e(date('d M Y', strtotime((string) ($a['published_at'] ?: $a['created_at'])))) ?></div>
                    <div class="small"><?= nl2br(e(mb_substr(strip_tags((string) $a['body']), 0, 220))) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php $this->end(); ?>
