<?php /* FILE: /app/Views/admin/tickets/index.php — all support tickets (admin) */ ?>
<?php $this->extend('layouts/admin'); $this->set('title', 'Support Tickets'); ?>
<?php $this->section('content'); ?>
<?php
    /** @var array $tickets  paginate() result: data,total,per_page,current_page,last_page */
    $rows = $tickets['data'] ?? [];
    $current = (string) ($status ?? '');
    $statusBadge = [
        'open' => 'warning', 'answered' => 'info', 'customer_reply' => 'warning',
        'on_hold' => 'muted', 'closed' => 'muted',
    ];
    $priorityBadge = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'muted'];
    $filters = ['' => 'બધી', 'open' => 'Open', 'answered' => 'Answered', 'customer_reply' => 'Customer Reply', 'on_hold' => 'On Hold', 'closed' => 'Closed'];
    $pageUrl = static function (string $s, int $p) {
        $q = [];
        if ($s !== '') { $q['status'] = $s; }
        if ($p > 1) { $q['page'] = $p; }
        return url('admin/tickets') . ($q ? '?' . http_build_query($q) : '');
    };
?>
<h2 class="page-title">🎫 Support Tickets</h2>

<div class="card">
    <div class="card-body">
        <div class="flex items-center gap-1" style="flex-wrap:wrap">
            <?php foreach ($filters as $key => $label): ?>
                <a href="<?= e($pageUrl((string) $key, 1)) ?>"
                   class="btn btn-sm <?= $current === (string) $key ? 'btn-primary' : 'btn-outline' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <span class="muted small" style="margin-left:auto">કુલ: <?= (int) ($tickets['total'] ?? 0) ?></span>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Subject</th>
                    <th>Client</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Last Reply</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center muted" style="padding:26px">કોઈ ટિકિટ મળી નથી.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $t): ?>
                        <?php
                            $client = trim((string) ($t['client_first_name'] ?? '') . ' ' . (string) ($t['client_last_name'] ?? ''));
                            if ($client === '') { $client = (string) ($t['client_company'] ?? '—'); }
                            $st = (string) ($t['status'] ?? 'open');
                            $pr = (string) ($t['priority'] ?? 'medium');
                        ?>
                        <tr>
                            <td class="mono"><a href="<?= e(url('admin/tickets/' . $t['id'])) ?>"><?= e($t['ticket_number']) ?></a></td>
                            <td><a href="<?= e(url('admin/tickets/' . $t['id'])) ?>"><?= e($t['subject']) ?></a></td>
                            <td><?= e($client) ?></td>
                            <td><?= e($t['department_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= e($priorityBadge[$pr] ?? 'muted') ?>"><?= e(ucfirst($pr)) ?></span></td>
                            <td><span class="badge badge-<?= e($statusBadge[$st] ?? 'muted') ?>"><?= e(ucfirst(str_replace('_', ' ', $st))) ?></span></td>
                            <td class="small muted"><?= e($t['last_reply_at'] ? date('d M Y, H:i', strtotime((string) $t['last_reply_at'])) : '—') ?></td>
                            <td class="text-right"><a href="<?= e(url('admin/tickets/' . $t['id'])) ?>" class="btn btn-sm btn-outline">જુઓ</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $cp = (int) ($tickets['current_page'] ?? 1); $lp = (int) ($tickets['last_page'] ?? 1); ?>
<?php if ($lp > 1): ?>
    <div class="flex items-center justify-between">
        <span class="muted small">પાનું <?= $cp ?> / <?= $lp ?></span>
        <div class="flex gap-1">
            <?php if ($cp > 1): ?><a href="<?= e($pageUrl($current, $cp - 1)) ?>" class="btn btn-sm btn-outline">← પાછળ</a><?php endif; ?>
            <?php if ($cp < $lp): ?><a href="<?= e($pageUrl($current, $cp + 1)) ?>" class="btn btn-sm btn-outline">આગળ →</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php $this->end(); ?>
