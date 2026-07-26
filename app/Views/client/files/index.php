<?php /* FILE: /app/Views/client/files/index.php — lightweight file manager */ ?>
<?php $this->extend('layouts/client'); $this->set('title', 'File Manager'); ?>
<?php $this->section('content'); ?>
<?php
$sid    = (int) $service['id'];
$parent = rtrim(dirname($path), '/');
// Only allow "up" while still inside the jail.
$canUp = $path !== $sitePath && str_starts_with($parent, $sitePath) && !str_contains($parent, '..');
?>

<div class="card mb-3">
    <div class="flex items-center justify-between">
        <div>
            <h3 style="margin:0">📁 File Manager — <?= e($service['domain']) ?></h3>
            <div class="small muted"><?= e($path) ?></div>
        </div>
        <a class="btn btn-sm btn-outline" href="<?= e(url('client/services/' . $sid)) ?>">← Service</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Type</th><th>Size</th></tr></thead>
            <tbody>
                <?php if ($canUp): ?>
                    <tr>
                        <td><a href="<?= e(url('client/services/' . $sid . '/files') . '?path=' . rawurlencode($parent)) ?>">📂 ..</a></td>
                        <td class="muted">up</td>
                        <td>—</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($dirs as $d): ?>
                    <?php if (($d['name'] ?? '') === '' || in_array($d['name'], ['.', '..'], true)) { continue; } ?>
                    <?php $child = rtrim($path, '/') . '/' . $d['name']; ?>
                    <tr>
                        <td>📂 <a href="<?= e(url('client/services/' . $sid . '/files') . '?path=' . rawurlencode($child)) ?>"><?= e($d['name']) ?></a></td>
                        <td class="muted">dir</td>
                        <td>—</td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($files as $f): ?>
                    <?php if (($f['name'] ?? '') === '') { continue; } ?>
                    <tr>
                        <td>📄 <?= e($f['name']) ?></td>
                        <td class="muted">file</td>
                        <td class="small muted"><?= e(is_numeric($f['size']) ? format_bytes((int) $f['size']) : (string) $f['size']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dirs) && empty($files) && !$canUp): ?>
                    <tr><td colspan="3" class="muted">આ ફોલ્ડર ખાલી છે અથવા ઍક્સેસ મળ્યું નથી.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <h3 style="margin:0 0 10px">✏️ Quick Edit / Save File</h3>
    <p class="small muted">site_path ની અંદરની full file path આપો (દા.ત. <?= e(rtrim($sitePath, '/')) ?>/index.php) અને content સેવ કરો.</p>
    <form method="post" action="<?= e(url('client/services/' . $sid . '/files/save')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>File Path</label>
            <input type="text" name="path" value="<?= e(rtrim($sitePath, '/')) ?>/" required>
        </div>
        <div class="form-group">
            <label>Content</label>
            <textarea name="data" rows="10" style="width:100%;font-family:monospace" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">💾 Save</button>
    </form>
</div>
<?php $this->end(); ?>
