<?php /* FILE: /app/Views/client/tickets/index.php — my tickets + new ticket (client) */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'Support'); ?>
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
    $depts = $departments ?? [];
    $prios = $priorities ?? ['low', 'medium', 'high', 'urgent'];
?>
<div class="flex items-center justify-between mb-2">
    <h2 class="page-title" style="margin:0">🎫 મદદ ટિકિટ (Support)</h2>
    <button type="button" class="btn btn-primary btn-sm" onclick="toggleNewTicket()">➕ નવી ટિકિટ</button>
</div>

<div id="new-ticket" class="card hidden">
    <div class="card-head">નવી ટિકિટ ખોલો</div>
    <div class="card-body">
        <form method="post" action="<?= e(url('client/tickets')) ?>">
            <?= csrf_field() ?>
            <div class="grid cols-2">
                <div class="form-group">
                    <label>વિભાગ (Department)</label>
                    <select name="department_id" required>
                        <option value="">— પસંદ કરો —</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?= e($d['id']) ?>" <?= (string) old('department_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>પ્રાથમિકતા (Priority)</label>
                    <select name="priority" required>
                        <?php foreach ($prios as $p): ?>
                            <option value="<?= e($p) ?>" <?= (old('priority', 'medium')) === $p ? 'selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>વિષય (Subject)</label>
                <input type="text" name="subject" value="<?= e(old('subject')) ?>" required>
            </div>
            <div class="form-group">
                <label>સંદેશ (Message)</label>
                <textarea name="message" rows="5" required><?= e(old('message')) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">ટિકિટ મોકલો</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Subject</th>
                    <th>Department</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Last Reply</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="7" class="text-center muted" style="padding:26px">હજી કોઈ ટિકિટ નથી. ઉપરથી નવી ટિકિટ ખોલો.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <?php $st = (string) ($t['status'] ?? 'open'); $pr = (string) ($t['priority'] ?? 'medium'); ?>
                        <tr>
                            <td class="mono"><a href="<?= e(url('client/tickets/' . $t['id'])) ?>"><?= e($t['ticket_number']) ?></a></td>
                            <td><a href="<?= e(url('client/tickets/' . $t['id'])) ?>"><?= e($t['subject']) ?></a></td>
                            <td><?= e($t['department_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= e($priorityBadge[$pr] ?? 'muted') ?>"><?= e(ucfirst($pr)) ?></span></td>
                            <td><span class="badge badge-<?= e($statusBadge[$st] ?? 'muted') ?>"><?= e($statusLabels[$st] ?? ucfirst($st)) ?></span></td>
                            <td class="small muted"><?= e($t['last_reply_at'] ? date('d M Y, H:i', strtotime((string) $t['last_reply_at'])) : '—') ?></td>
                            <td class="text-right"><a href="<?= e(url('client/tickets/' . $t['id'])) ?>" class="btn btn-sm btn-outline">જુઓ</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->end(); ?>

<?php $this->section('scripts'); ?>
<script>
function toggleNewTicket() {
    var el = document.getElementById('new-ticket');
    if (el) { el.classList.toggle('hidden'); if (!el.classList.contains('hidden')) { el.scrollIntoView({ behavior: 'smooth' }); } }
}
<?php if (\App\Core\Session::has('errors') && (string) old('subject') !== ''): ?>
document.addEventListener('DOMContentLoaded', function () { var el = document.getElementById('new-ticket'); if (el) el.classList.remove('hidden'); });
<?php endif; ?>
</script>
<?php $this->end(); ?>
