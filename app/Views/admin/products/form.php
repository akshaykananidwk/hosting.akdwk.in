<?php // FILE: /app/Views/admin/products/form.php — create/edit product + pricing
$this->extend('layouts/admin'); $this->set('title', $isEdit ? 'Edit Product' : 'New Product');
$v = fn($k, $d = '') => e(old($k, $product[$k] ?? $d));
$pv = fn($k) => e(old($k, ($pricing[$k] ?? '') === null ? '' : ($pricing[$k] ?? '')));
$phpVers = ['74' => 'PHP 7.4', '80' => 'PHP 8.0', '81' => 'PHP 8.1', '82' => 'PHP 8.2', '83' => 'PHP 8.3'];
$freqs = ['none' => 'None', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
?>
<?php $this->section('content'); ?>

<div class="card" style="max-width:820px">
    <div class="card-head"><?= $isEdit ? 'Product Edit કરો' : 'નવી Product' ?></div>
    <div class="card-body">
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <div class="grid cols-2">
                <div class="form-group">
                    <label>Plan Name *</label>
                    <input type="text" name="name" value="<?= $v('name') ?>" required>
                </div>
                <div class="form-group">
                    <label>Product Group *</label>
                    <select name="group_id" required>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= (int) $g['id'] ?>" <?= (int) old('group_id', $product['group_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2"><?= $v('description') ?></textarea>
            </div>

            <h4 class="mt-3">Resources</h4>
            <div class="grid cols-3">
                <div class="form-group"><label>Disk (MB)</label><input type="number" name="disk_mb" value="<?= $v('disk_mb', '1024') ?>"></div>
                <div class="form-group"><label>Bandwidth (MB)</label><input type="number" name="bandwidth_mb" value="<?= $v('bandwidth_mb', '20480') ?>"></div>
                <div class="form-group">
                    <label>Bandwidth Unlimited?</label>
                    <label class="small"><input type="checkbox" name="is_bw_unlimited" value="1" <?= (int) old('is_bw_unlimited', $product['is_bw_unlimited'] ?? 0) === 1 ? 'checked' : '' ?>> Unlimited</label>
                </div>
                <div class="form-group"><label>Max Sites</label><input type="number" name="max_sites" value="<?= $v('max_sites', '1') ?>"></div>
                <div class="form-group"><label>Max Databases</label><input type="number" name="max_databases" value="<?= $v('max_databases', '1') ?>"></div>
                <div class="form-group"><label>Max FTP</label><input type="number" name="max_ftp" value="<?= $v('max_ftp', '1') ?>"></div>
                <div class="form-group">
                    <label>PHP Version</label>
                    <select name="php_version">
                        <?php foreach ($phpVers as $pk => $pl): ?>
                            <option value="<?= $pk ?>" <?= (string) old('php_version', $product['php_version'] ?? '82') === $pk ? 'selected' : '' ?>><?= $pl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Backup Frequency</label>
                    <select name="backup_freq">
                        <?php foreach ($freqs as $fk => $fl): ?>
                            <option value="<?= $fk ?>" <?= (string) old('backup_freq', $product['backup_freq'] ?? 'daily') === $fk ? 'selected' : '' ?>><?= $fl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Free SSL?</label>
                    <label class="small"><input type="checkbox" name="free_ssl" value="1" <?= (int) old('free_ssl', $product['free_ssl'] ?? 1) === 1 ? 'checked' : '' ?>> Free Let's Encrypt</label>
                </div>
            </div>

            <h4 class="mt-3">Pricing (₹ INR)</h4>
            <div class="grid cols-4">
                <div class="form-group"><label>Monthly</label><input type="number" step="0.01" name="monthly" value="<?= $pv('monthly') ?>"></div>
                <div class="form-group"><label>Quarterly</label><input type="number" step="0.01" name="quarterly" value="<?= $pv('quarterly') ?>"></div>
                <div class="form-group"><label>Half-Yearly</label><input type="number" step="0.01" name="half_yearly" value="<?= $pv('half_yearly') ?>"></div>
                <div class="form-group"><label>Yearly</label><input type="number" step="0.01" name="yearly" value="<?= $pv('yearly') ?>"></div>
            </div>

            <div class="grid cols-2">
                <div class="form-group"><label>Setup Fee (₹)</label><input type="number" step="0.01" name="setup_fee" value="<?= $v('setup_fee', '0') ?>"></div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['active' => 'Active', 'hidden' => 'Hidden', 'retired' => 'Retired'] as $sk => $sl): ?>
                            <option value="<?= $sk ?>" <?= (string) old('status', $product['status'] ?? 'active') === $sk ? 'selected' : '' ?>><?= $sl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex gap-1 mt-3">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'અપડેટ કરો' : 'બનાવો' ?></button>
                <a href="<?= e(url('admin/products')) ?>" class="btn btn-outline">રદ કરો</a>
            </div>
        </form>
    </div>
</div>

<?php $this->end(); ?>
