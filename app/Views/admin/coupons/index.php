<?php // FILE: /app/Views/admin/coupons/index.php — coupon list + inline create
$this->extend('layouts/admin'); $this->set('title', 'Coupons (કૂપન)');
$cBadge = ['active' => 'success', 'disabled' => 'muted'];
?>

<div class="grid cols-2">
    <div class="card">
        <div class="card-head">નવી કૂપન</div>
        <div class="card-body">
            <form method="post" action="<?= e(url('admin/coupons')) ?>">
                <?= csrf_field() ?>
                <div class="form-group"><label>Code *</label><input type="text" name="code" value="<?= e(old('code')) ?>" placeholder="WELCOME10" required></div>
                <div class="grid cols-2">
                    <div class="form-group">
                        <label>Type *</label>
                        <select name="type">
                            <option value="percent" <?= old('type') === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
                            <option value="fixed" <?= old('type') === 'fixed' ? 'selected' : '' ?>>Fixed (₹)</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Value *</label><input type="number" step="0.01" name="value" value="<?= e(old('value')) ?>" required></div>
                </div>
                <div class="grid cols-2">
                    <div class="form-group">
                        <label>Applies To</label>
                        <select name="applies_to">
                            <option value="all">All</option>
                            <option value="product">Product</option>
                            <option value="group">Group</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Min Amount (₹)</label><input type="number" step="0.01" name="min_amount" value="<?= e(old('min_amount', '0')) ?>"></div>
                </div>
                <div class="grid cols-2">
                    <div class="form-group"><label>Max Uses</label><input type="number" name="max_uses" value="<?= e(old('max_uses')) ?>" placeholder="unlimited"></div>
                    <div class="form-group"><label>Per Client</label><input type="number" name="per_client" value="<?= e(old('per_client', '1')) ?>"></div>
                </div>
                <div class="grid cols-2">
                    <div class="form-group"><label>Starts</label><input type="date" name="starts_at" value="<?= e(old('starts_at')) ?>"></div>
                    <div class="form-group"><label>Expires</label><input type="date" name="expires_at" value="<?= e(old('expires_at')) ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary">કૂપન બનાવો</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head">બધી કૂપન <span class="muted small">(<?= count($coupons) ?>)</span></div>
        <div class="card-body table-wrap">
            <table class="table">
                <thead><tr><th>Code</th><th>Value</th><th>Used</th><th>Expires</th><th>સ્થિતિ</th></tr></thead>
                <tbody>
                    <?php if (empty($coupons)): ?><tr><td colspan="5" class="text-center muted">કોઈ કૂપન નથી</td></tr><?php endif; ?>
                    <?php foreach ($coupons as $c): ?>
                        <tr>
                            <td class="mono"><?= e($c['code']) ?></td>
                            <td><?= $c['type'] === 'percent' ? e(rtrim(rtrim((string) $c['value'], '0'), '.')) . '%' : e(money($c['value'])) ?></td>
                            <td class="small"><?= (int) $c['used_count'] ?><?= $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '' ?></td>
                            <td class="small"><?= e($c['expires_at'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $cBadge[$c['status']] ?? 'muted' ?>"><?= e($c['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

