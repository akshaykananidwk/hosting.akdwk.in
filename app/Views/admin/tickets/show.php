<?php /* FILE: /app/Views/admin/tickets/show.php — ticket thread + reply (admin) */ ?>
<?php $this->extend('layouts/admin'); $this->set('title', 'Ticket ' . ($ticket['ticket_number'] ?? '')); ?>
<?php $this->section('content'); ?>
<?php
    $statusBadge = [
        'open' => 'warning', 'answered' => 'info', 'customer_reply' => 'warning',
        'on_hold' => 'muted', 'closed' => 'muted',
    ];
    $priorityBadge = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'muted'];
    $st = (string) ($ticket['status'] ?? 'open');
    $pr = (string) ($ticket['priority'] ?? 'medium');
    $clientName = trim((string) ($client['first_name'] ?? '') . ' ' . (string) ($client['last_name'] ?? ''));
    if ($clientName === '') { $clientName = (string) ($client['company'] ?? '—'); }
    $statusLabels = [
        'open' => 'Open', 'answered' => 'Answered', 'customer_reply' => 'Customer Reply',
        'on_hold' => 'On Hold', 'closed' => 'Closed',
    ];
?>
<div class="flex items-center justify-between mb-2">
    <h2 class="page-title" style="margin:0">🎫 <?= e($ticket['ticket_number']) ?></h2>
    <a href="<?= e(url('admin/tickets')) ?>" class="btn btn-sm btn-outline">← All Tickets</a>
</div>

<div class="card">
    <div class="card-body">
        <h3 style="margin-bottom:10px"><?= e($ticket['subject']) ?></h3>
        <div class="flex items-center gap-2" style="flex-wrap:wrap">
            <span class="badge badge-<?= e($statusBadge[$st] ?? 'muted') ?>"><?= e($statusLabels[$st] ?? ucfirst($st)) ?></span>
            <span class="badge badge-<?= e($priorityBadge[$pr] ?? 'muted') ?>"><?= e(ucfirst($pr)) ?> priority</span>
            <span class="muted small">👤 <?= e($clientName) ?><?= !empty($client['email']) ? ' · ' . e($client['email']) : '' ?></span>
            <span class="muted small">🗂️ <?= e($department['name'] ?? '—') ?></span>
            <span class="muted small">🕒 <?= e(date('d M Y, H:i', strtotime((string) $ticket['created_at']))) ?></span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">Conversation</div>
    <div class="card-body">
        <?php if (empty($replies)): ?>
            <p class="muted">No messages.</p>
        <?php else: ?>
            <?php foreach ($replies as $r): ?>
                <?php
                    $isStaff = ($r['author_type'] ?? 'client') === 'staff';
                    $isInternal = (int) ($r['is_internal'] ?? 0) === 1;
                    $isSystem = ($r['author_type'] ?? '') === 'system';
                    $accent = $isInternal ? 'var(--warning)' : ($isStaff ? 'var(--primary)' : 'var(--border)');
                    $bg = $isInternal ? 'rgba(217,119,6,.08)' : 'var(--surface-2)';
                ?>
                <div style="border:1px solid var(--border);border-left:3px solid <?= $accent ?>;background:<?= $bg ?>;border-radius:8px;padding:12px 14px;margin-bottom:12px">
                    <div class="flex items-center justify-between mb-2">
                        <strong class="small">
                            <?= $isSystem ? '⚙️ System' : ($isStaff ? '🧑‍💼 Staff' : '👤 ' . e($clientName)) ?>
                            <?php if ($isInternal): ?><span class="badge badge-warning" style="margin-left:6px">Internal Note</span><?php endif; ?>
                        </strong>
                        <span class="muted small"><?= e(date('d M Y, H:i', strtotime((string) $r['created_at']))) ?></span>
                    </div>
                    <div style="white-space:pre-wrap"><?= nl2br(e($r['message'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head">Reply</div>
    <div class="card-body">
        <form method="post" action="<?= e(url('admin/tickets/' . $ticket['id'] . '/reply')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" rows="5" required><?= e(old('message')) ?></textarea>
            </div>
            <div class="grid cols-2">
                <div class="form-group">
                    <label>Status Change</label>
                    <select name="status">
                        <?php foreach (($statuses ?? []) as $s): ?>
                            <option value="<?= e($s) ?>" <?= $s === $st ? 'selected' : '' ?>><?= e($statusLabels[$s] ?? ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <label style="display:flex;align-items:center;gap:8px;margin:0;font-weight:500">
                        <input type="checkbox" name="is_internal" value="1" style="width:auto">
                        Internal note (not visible to the client)
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Send Reply</button>
        </form>
    </div>
</div>
<?php $this->end(); ?>
