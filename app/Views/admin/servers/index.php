<?php $this->extend('layouts/admin'); $this->set('title', 'Servers'); ?>
<?php
// FILE: /app/Views/admin/servers/index.php — aaPanel servers list
/** @var array $servers */
$statusBadge = ['online' => 'success', 'offline' => 'danger', 'maintenance' => 'warning', 'disabled' => 'muted'];
?>
<?php /* Content renders at top level for this View engine; only 'scripts' uses a named section. */ ?>

<div class="card">
    <div class="card-head">
        <span>🖥️ aaPanel Servers</span>
        <a href="<?= e(url('admin/servers/create')) ?>" class="btn btn-primary btn-sm">+ Server ઉમેરો</a>
    </div>
    <div class="card-body">
        <?php if (empty($servers)): ?>
            <p class="muted">કોઈ server નથી. પહેલો aaPanel server ઉમેરો.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th><th>Panel URL</th><th>Status</th>
                        <th>RAM</th><th>Disk</th><th>Accounts</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($servers as $s): ?>
                    <tr>
                        <td>
                            <strong><?= e($s['name']) ?></strong>
                            <?php if (!empty($s['ip_address'])): ?>
                                <div class="small muted mono"><?= e($s['ip_address']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small mono"><?= e($s['panel_url']) ?></td>
                        <td><span class="badge badge-<?= e($statusBadge[$s['status']] ?? 'muted') ?>"><?= e($s['status']) ?></span></td>
                        <td class="small"><?= e(number_format((float) ($s['ram_percent'] ?? 0), 1)) ?>%</td>
                        <td class="small"><?= e(number_format((float) ($s['disk_percent'] ?? 0), 1)) ?>%</td>
                        <td class="small"><?= e((int) ($s['active_accounts'] ?? 0)) ?> / <?= e((int) ($s['max_accounts'] ?? 0)) ?></td>
                        <td>
                            <div class="flex gap-1">
                                <button class="btn btn-sm btn-outline" data-test="<?= e((int) $s['id']) ?>">Test</button>
                                <button class="btn btn-sm btn-outline" data-import="<?= e((int) $s['id']) ?>">Import Sites</button>
                            </div>
                            <div class="small mt-1" id="srv-result-<?= e((int) $s['id']) ?>"></div>
                        </td>
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
    var testUrl = <?= json_encode(rtrim((string) config('app.url', ''), '/') . '/admin/servers/') ?>;

    function busy(el, on) {
        el.disabled = on;
        el.dataset.label = el.dataset.label || el.textContent;
        el.textContent = on ? '…' : el.dataset.label;
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-test]');
        var im = e.target.closest('[data-import]');
        if (t) {
            var id = t.getAttribute('data-test');
            var box = document.getElementById('srv-result-' + id);
            busy(t, true);
            box.innerHTML = 'Testing…';
            window.akc.post(testUrl + id + '/test', {}).then(function (r) {
                busy(t, false);
                if (r.ok) {
                    box.innerHTML = '<span class="badge badge-success">Connected ✅</span>';
                } else {
                    box.innerHTML = '<span class="badge badge-danger">Failed</span> <span class="muted">' +
                        (r.error ? String(r.error).replace(/[<>&]/g, '') : '') + '</span>';
                }
            }).catch(function () { busy(t, false); box.innerHTML = '<span class="badge badge-danger">Error</span>'; });
        }
        if (im) {
            var iid = im.getAttribute('data-import');
            var ibox = document.getElementById('srv-result-' + iid);
            busy(im, true);
            ibox.innerHTML = 'Loading sites…';
            window.akc.post(testUrl + iid + '/import', {}).then(function (r) {
                busy(im, false);
                if (r.ok) {
                    ibox.innerHTML = '<span class="badge badge-info">' + (r.count || 0) + ' sites મળી</span> <span class="muted">' +
                        (r.note ? String(r.note).replace(/[<>&]/g, '') : '') + '</span>';
                } else {
                    ibox.innerHTML = '<span class="badge badge-danger">' +
                        (r.error ? String(r.error).replace(/[<>&]/g, '') : 'Failed') + '</span>';
                }
            }).catch(function () { busy(im, false); ibox.innerHTML = '<span class="badge badge-danger">Error</span>'; });
        }
    });
})();
</script>
<?php $this->end(); ?>
