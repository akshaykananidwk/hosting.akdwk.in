<?php // FILE: /app/Views/admin/templates/index.php — WhatsApp + Email templates
$this->extend('layouts/admin'); $this->set('title', 'Templates (ટેમ્પ્લેટ)');
$vars = function (string $body): array {
    preg_match_all('/\{([a-z0-9_]+)\}/i', $body, $m);
    return array_values(array_unique($m[1] ?? []));
};
?>

<div class="flex gap-1 mb-3">
    <button class="btn btn-primary btn-sm" type="button" onclick="tplTab('wa')" id="tab-wa">💬 WhatsApp (<?= count($whatsapp) ?>)</button>
    <button class="btn btn-outline btn-sm" type="button" onclick="tplTab('em')" id="tab-em">✉️ Email (<?= count($email) ?>)</button>
</div>

<div id="pane-wa">
    <?php foreach ($whatsapp as $t): $vlist = $vars((string) $t['body']); ?>
        <div class="card">
            <div class="card-head">
                <span><?= e($t['name']) ?> <span class="mono small muted">{<?= e($t['slug']) ?>}</span></span>
                <span class="badge <?= (int) $t['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $t['is_active'] === 1 ? 'active' : 'off' ?></span>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/templates/' . $t['id'] . '?type=whatsapp')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="body" rows="5"><?= e($t['body']) ?></textarea>
                    </div>
                    <?php if ($vlist): ?>
                        <div class="form-hint mb-2">Variables:
                            <?php foreach ($vlist as $vv): ?><span class="badge badge-muted mono">{<?= e($vv) ?>}</span> <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">સેવ કરો</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($whatsapp)): ?><div class="alert alert-info">કોઈ WhatsApp template નથી.</div><?php endif; ?>
</div>

<div id="pane-em" class="hidden">
    <?php foreach ($email as $t): $vlist = $vars((string) $t['subject'] . ' ' . (string) $t['body']); ?>
        <div class="card">
            <div class="card-head">
                <span><?= e($t['name']) ?> <span class="mono small muted">{<?= e($t['slug']) ?>}</span></span>
                <span class="badge <?= (int) $t['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $t['is_active'] === 1 ? 'active' : 'off' ?></span>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/templates/' . $t['id'] . '?type=email')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" value="<?= e($t['subject']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Body (HTML)</label>
                        <textarea name="body" rows="6"><?= e($t['body']) ?></textarea>
                    </div>
                    <?php if ($vlist): ?>
                        <div class="form-hint mb-2">Variables:
                            <?php foreach ($vlist as $vv): ?><span class="badge badge-muted mono">{<?= e($vv) ?>}</span> <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">સેવ કરો</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($email)): ?><div class="alert alert-info">કોઈ Email template નથી.</div><?php endif; ?>
</div>

<?php $this->section('scripts'); ?>
<script>
function tplTab(which) {
    document.getElementById('pane-wa').classList.toggle('hidden', which !== 'wa');
    document.getElementById('pane-em').classList.toggle('hidden', which !== 'em');
    var wa = document.getElementById('tab-wa'), em = document.getElementById('tab-em');
    wa.className = 'btn btn-sm ' + (which === 'wa' ? 'btn-primary' : 'btn-outline');
    em.className = 'btn btn-sm ' + (which === 'em' ? 'btn-primary' : 'btn-outline');
}
</script>
<?php $this->end(); ?>
