<?php $this->extend('layouts/admin'); $this->set('title', 'Updates'); ?>
<?php
// FILE: /app/Views/admin/updates/index.php — GitHub auto-update panel
/** @var string $currentVersion @var array $history @var bool $canRun */
$histBadge = ['success' => 'success', 'failed' => 'danger', 'rolled_back' => 'warning'];
?>
<?php /* Content renders at top level for this View engine; only 'scripts' uses a named section. */ ?>

<div class="card">
    <div class="card-head"><span>🔄 System Update</span></div>
    <div class="card-body">
        <div class="flex items-center justify-between mb-3">
            <div>
                <div class="small muted">Current Version</div>
                <div class="value" style="font-size:1.5rem;font-weight:700"><?= e($currentVersion) ?></div>
            </div>
            <div class="flex gap-1">
                <button id="check-update" class="btn btn-outline">Update Check</button>
                <?php if ($canRun): ?>
                    <button id="run-update" class="btn btn-primary" disabled>Update Run</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$canRun): ?>
            <div class="alert alert-warning">Update Only <strong>super_admin</strong> can run this.</div>
        <?php endif; ?>

        <div id="update-result"></div>
    </div>
</div>

<div class="card">
    <div class="card-head"><span>🕑 Update History</span></div>
    <div class="card-body">
        <?php if (empty($history)): ?>
            <p class="muted">No updates have been run yet.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>From → To</th><th>Commit</th><th>Status</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="small"><?= e($h['from_version'] ?? '—') ?> → <?= e($h['to_version'] ?? '—') ?></td>
                        <td class="small mono"><?= e($h['commit_hash'] ?? '—') ?></td>
                        <td><span class="badge badge-<?= e($histBadge[$h['status']] ?? 'muted') ?>"><?= e($h['status']) ?></span></td>
                        <td class="small muted"><?= e($h['created_at']) ?></td>
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
    var base = <?= json_encode(rtrim((string) config('app.url', ''), '/') . '/admin/updates') ?>;
    var checkBtn = document.getElementById('check-update');
    var runBtn = document.getElementById('run-update');
    var box = document.getElementById('update-result');

    function esc(s) { return String(s == null ? '' : s).replace(/[<>&]/g, ''); }

    if (checkBtn) {
        checkBtn.addEventListener('click', function () {
            checkBtn.disabled = true;
            box.innerHTML = '<div class="alert alert-info">GitHub Checking…</div>';
            window.akc.post(base + '/check', {}).then(function (r) {
                checkBtn.disabled = false;
                if (r.error) {
                    box.innerHTML = '<div class="alert alert-danger">' + esc(r.error) + '</div>';
                    return;
                }
                if (r.available) {
                    box.innerHTML = '<div class="alert alert-warning">A new update is available! ' +
                        'Commit <span class="mono">' + esc(r.latest_sha) + '</span> — ' + esc(r.message) + '</div>';
                    if (runBtn) { runBtn.disabled = false; }
                } else {
                    box.innerHTML = '<div class="alert alert-success">System up-to-date ✅ (' + esc(r.latest_sha || r.current_version) + ')</div>';
                    if (runBtn) { runBtn.disabled = true; }
                }
            }).catch(function () {
                checkBtn.disabled = false;
                box.innerHTML = '<div class="alert alert-danger">Check request failed.</div>';
            });
        });
    }

    if (runBtn) {
        runBtn.addEventListener('click', function () {
            if (!window.confirm('Update Run the update? A backup will be taken first.')) { return; }
            runBtn.disabled = true;
            runBtn.textContent = 'Update Running…';
            box.innerHTML = '<div class="alert alert-info">Update process In progress — do not close this page…</div>';
            window.akc.post(base + '/run', {}).then(function (r) {
                runBtn.textContent = 'Update Run';
                var cls = r.ok ? 'alert-success' : 'alert-danger';
                box.innerHTML = '<div class="alert ' + cls + '">' + esc(r.message) + '</div>';
                if (r.ok) { setTimeout(function () { window.location.reload(); }, 2500); }
                else { runBtn.disabled = false; }
            }).catch(function () {
                runBtn.textContent = 'Update Run';
                runBtn.disabled = false;
                box.innerHTML = '<div class="alert alert-danger">Update request failed.</div>';
            });
        });
    }
})();
</script>
<?php $this->end(); ?>
