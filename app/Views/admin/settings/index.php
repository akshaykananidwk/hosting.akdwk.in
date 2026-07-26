<?php // FILE: /app/Views/admin/settings/index.php — tabbed system settings
$this->extend('layouts/admin'); $this->set('title', 'Settings (Settings)');
?>

<form method="post" action="<?= e(url('admin/settings')) ?>">
    <?= csrf_field() ?>

    <div class="flex gap-1 mb-3" style="flex-wrap:wrap">
        <?php foreach ($schema as $i => $tab): ?>
            <button type="button" id="stab-<?= e($tab['id']) ?>"
                    class="btn btn-sm <?= $i === 0 ? 'btn-primary' : 'btn-outline' ?>"
                    onclick="setTab('<?= e($tab['id']) ?>')"><?= e($tab['label']) ?></button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($schema as $i => $tab): ?>
        <div class="card settings-pane<?= $i === 0 ? '' : ' hidden' ?>" id="spane-<?= e($tab['id']) ?>">
            <div class="card-head"><?= e($tab['label']) ?></div>
            <div class="card-body">
                <div class="grid cols-2">
                    <?php foreach ($tab['fields'] as $f):
                        $full = $f['group'] . '.' . $f['key'];
                        $name = 's[' . $full . ']';
                        $cur = settings($full, '');
                        $type = $f['type'];
                        ?>
                        <div class="form-group"<?= $type === 'textarea' ? ' style="grid-column:1/-1"' : '' ?>>
                            <label><?= e($f['label']) ?></label>
                            <?php if ($type === 'bool'): ?>
                                <label class="small"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= (string) $cur === '1' ? 'checked' : '' ?>> Enabled</label>
                            <?php elseif ($type === 'secret'): ?>
                                <input type="password" name="<?= e($name) ?>" value="" autocomplete="new-password" placeholder="<?= ((string) $cur !== '') ? '•••••••• (set)' : 'Empty' ?>">
                                <div class="form-hint">Leave blank to keep the current value<?= ((string) $cur !== '') ? ' · **** Set' : '' ?></div>
                            <?php elseif ($type === 'select'): ?>
                                <select name="<?= e($name) ?>">
                                    <?php foreach (($f['options'] ?? []) as $ok => $ol): ?>
                                        <option value="<?= e($ok) ?>" <?= (string) $cur === (string) $ok ? 'selected' : '' ?>><?= e($ol) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($type === 'textarea'): ?>
                                <textarea name="<?= e($name) ?>" rows="3"><?= e($cur) ?></textarea>
                            <?php elseif ($type === 'number'): ?>
                                <input type="number" step="any" name="<?= e($name) ?>" value="<?= e($cur) ?>">
                            <?php else: ?>
                                <input type="text" name="<?= e($name) ?>" value="<?= e($cur) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Save All</button>
</form>

<?php $this->section('scripts'); ?>
<script>
function setTab(id) {
    document.querySelectorAll('.settings-pane').forEach(function (p) {
        p.classList.toggle('hidden', p.id !== 'spane-' + id);
    });
    document.querySelectorAll('[id^="stab-"]').forEach(function (b) {
        var on = b.id === 'stab-' + id;
        b.className = 'btn btn-sm ' + (on ? 'btn-primary' : 'btn-outline');
    });
}
</script>
<?php $this->end(); ?>
