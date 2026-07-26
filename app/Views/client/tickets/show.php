<?php /* FILE: /app/Views/client/tickets/show.php — ticket thread + reply (client) */ ?>
<?php $this->extend('layouts/client'); $this->set('title', $ticket['subject'] ?? 'Ticket'); ?>
<?php $this->section('content'); ?>
<?php
    $statusBadge = [
        'open' => 'warning', 'answered' => 'info', 'customer_reply' => 'warning',
        'on_hold' => 'muted', 'closed' => 'muted',
    ];
    $priorityBadge = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'muted'];
    $statusLabels = [
        'open' => 'Open', 'answered' => 'Answered', 'customer_reply' => 'Awaiting Reply',
        'on_hold' => 'On Hold', 'closed' => 'Closed',
    ];
    $st = (string) ($ticket['status'] ?? 'open');
    $pr = (string) ($ticket['priority'] ?? 'medium');
    $clientName = trim((string) ($client['first_name'] ?? '') . ' ' . (string) ($client['last_name'] ?? ''));
    if ($clientName === '') { $clientName = (string) ($client['company'] ?? 'You'); }
    $isClosed = $st === 'closed';
?>
<div class="flex items-center justify-between mb-2">
    <h2 class="page-title" style="margin:0">🎫 <?= e($ticket['ticket_number']) ?></h2>
    <a href="<?= e(url('client/tickets')) ?>" class="btn btn-sm btn-outline">← All Tickets</a>
</div>

<div class="card">
    <div class="card-body">
        <h3 style="margin-bottom:10px"><?= e($ticket['subject']) ?></h3>
        <div class="flex items-center gap-2" style="flex-wrap:wrap">
            <span class="badge badge-<?= e($statusBadge[$st] ?? 'muted') ?>"><?= e($statusLabels[$st] ?? ucfirst($st)) ?></span>
            <span class="badge badge-<?= e($priorityBadge[$pr] ?? 'muted') ?>"><?= e(ucfirst($pr)) ?> priority</span>
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
                    $isSystem = ($r['author_type'] ?? '') === 'system';
                    $accent = $isStaff ? 'var(--primary)' : 'var(--border)';
                ?>
                <div style="border:1px solid var(--border);border-left:3px solid <?= $accent ?>;background:var(--surface-2);border-radius:8px;padding:12px 14px;margin-bottom:12px">
                    <div class="flex items-center justify-between mb-2">
                        <strong class="small"><?= $isSystem ? '⚙️ System' : ($isStaff ? '🧑‍💼 Support Team' : '👤 ' . e($clientName)) ?></strong>
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
        <?php if ($isClosed): ?>
            <p class="muted">This ticket is closed. Please open a new ticket for a new issue.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('client/tickets/' . $ticket['id'] . '/reply')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="5" required><?= e(old('message')) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Reply</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php $this->end(); ?>
