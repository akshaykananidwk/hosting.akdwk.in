<?php /* FILE: /app/Views/store/configure.php — order configuration form */ ?>
<?php $this->extend('layouts/guest'); $this->set('title', 'ઓર્ડર કન્ફિગર'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-2"><?= e($product['name']) ?></h3>
<?php if (!empty($product['description'])): ?>
    <p class="muted small text-center mb-3"><?= e($product['description']) ?></p>
<?php endif; ?>

<div class="card"><div class="card-body">
    <div class="small muted">
        💾 ડિસ્ક: <?= e(format_mb((int) $product['disk_mb'])) ?><br>
        📶 બેન્ડવિડ્થ: <?= ((int) $product['is_bw_unlimited'] === 1)
            ? 'Unlimited' : e(format_mb((int) $product['bandwidth_mb'])) ?><br>
        🐘 PHP: <?= e($product['php_version']) ?>
        &nbsp;·&nbsp; 🔒 Free SSL: <?= ((int) ($product['free_ssl'] ?? 0) === 1) ? 'હા' : 'ના' ?>
    </div>
</div></div>

<?php if (empty($cycles)): ?>
    <div class="alert alert-warning">આ પ્રોડક્ટ માટે કિંમત સેટ થઈ નથી. કૃપા કરી પછી પ્રયત્ન કરો.</div>
    <div class="text-center small mt-3"><a href="<?= e(url('store')) ?>">← બધા પ્લાન</a></div>
<?php else: ?>
    <?php if (auth()->guest()): ?>
        <div class="alert alert-info small">ઓર્ડર પૂરો કરવા માટે લોગિન જરૂરી છે — ડોમેન નાખીને આગળ વધો.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('order')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">

        <div class="form-group">
            <label>ડોમેન નામ</label>
            <input type="text" name="domain" value="<?= e(old('domain')) ?>" placeholder="example.com" required>
            <div class="form-hint">તમારું ડોમેન નાખો (દા.ત. mysite.com).</div>
        </div>

        <div class="form-group">
            <label>બિલિંગ સાયકલ</label>
            <select name="billing_cycle" required>
                <?php foreach ($cycles as $cy): ?>
                    <option value="<?= e($cy['key']) ?>"><?= e($cy['label']) ?> — <?= e(money($cy['price'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>કૂપન કોડ (વૈકલ્પિક)</label>
            <input type="text" name="coupon" value="<?= e(old('coupon')) ?>" placeholder="DISCOUNT10">
        </div>

        <button type="submit" class="btn btn-primary btn-block">આગળ વધો → ચેકઆઉટ</button>
    </form>

    <div class="text-center small mt-3"><a href="<?= e(url('store')) ?>">← બધા પ્લાન</a></div>
<?php endif; ?>
<?php $this->end(); ?>
