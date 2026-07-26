<?php $this->extend('layouts/admin'); $this->set('title', 'Backups'); ?>
<?php
// FILE: /app/Views/admin/backups/index.php — system backups list + run now
/** @var array $backups */
$statusBadge = ['completed' => 'success', 'running' => 'info', 'pending' => 'warning', 'failed' => 'danger'];
?>
<?php /* Content renders at top level for this View engine; only 'scripts' uses a named section. */ ?>

<div class="card">
    <div class="card-head">
        <span>💾 System Backups</span>
        <button id="run-backup" class="btn btn-primary btn-sm">Backup Run now</button>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            Last <strong>10</strong> backups are retained (older ones are auto-deleted). A backup contains all app files plus a DB dump.
        </div>
        <div id="backup-msg"></div>

        <?php if (empty($backups)): ?>
            <p class="muted">No backups yet.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Filename</th><th>Type</th><th>Destination</th><th>Size</th><th>Status</th><th>Time</th></tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td class="mono small"><?= e($b['filename'] ?? '—') ?></td>
                        <td><?= e($b['type']) ?></td>
                        <td><?= e($b['destination']) ?></td>
                        <td class="small"><?= e($b['size'] !== null ? format_bytes((int) $b['size']) : '—') ?></td>
                        <td><span class="badge badge-<?= e($statusBadge[$b['status']] ?? 'muted') ?>"><?= e($b['status']) ?></span></td>
                        <td class="small muted"><?= e($b['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php /* end content */ ?>

<?php $this->section('scripts'); ?>
<script>
(function () {
    'use strict';
    var btn = document.getElementById('run-backup');
    var msg = document.getElementById('backup-msg');
    if (!btn) { return; }
    var runUrl = <?= json_encode(rtrim((string) config('app.url', ''), '/') . '/admin/backups/run') ?>;

    btn.addEventListener('click', function () {
        if (!window.confirm('Full backup Run it? This may take a while.')) { return; }
        btn.disabled = true;
        btn.textContent = 'Backup Running…';
        msg.innerHTML = '<div class="alert alert-info">Backup process In progress, please wait…</div>';

        var body = new FormData();
        body.append('_token', window.akc.token());
        fetch(runUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.akc.token() },
            body: body,
        }).then(function () {
            // Controller flashes success/error, then redirects — reload to show it + new row.
            window.location.reload();
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Backup Run now';
            msg.innerHTML = '<div class="alert alert-danger">Backup request failed.</div>';
        });
    });
})();
</script>
<?php $this->end(); ?>
