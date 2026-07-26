<?php /* FILE: /app/Views/store/catalog.php — storefront product catalog */ ?>
<?php $this->extend('layouts/guest'); $this->set('title', 'Store — Hosting Plans'); ?>
<?php $this->section('content'); ?>
<h3 class="text-center mb-3">☁️ Choose a hosting plan</h3>

<?php if (empty($groups)): ?>
    <p class="muted text-center">No plans are available right now.</p>
<?php else: ?>
    <?php foreach ($groups as $group): ?>
        <div class="mb-3">
            <h4><?= e($group['name']) ?></h4>
            <?php if (!empty($group['description'])): ?>
                <p class="muted small"><?= e($group['description']) ?></p>
            <?php endif; ?>

            <?php if (empty($group['products'])): ?>
                <p class="muted small">No products in this group.</p>
            <?php else: ?>
                <?php foreach ($group['products'] as $p): ?>
                    <div class="card"><div class="card-body">
                        <div class="flex justify-between items-center gap-2">
                            <div>
                                <strong><?= e($p['name']) ?></strong>
                                <?php if (!empty($p['description'])): ?>
                                    <div class="muted small"><?= e($p['description']) ?></div>
                                <?php endif; ?>
                                <div class="small mt-1 muted">
                                    💾 <?= e(format_mb((int) $p['disk_mb'])) ?>
                                    &nbsp;·&nbsp;
                                    📶 <?= ((int) $p['is_bw_unlimited'] === 1)
                                        ? 'Unlimited BW'
                                        : e(format_mb((int) $p['bandwidth_mb'])) . ' BW' ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if ($p['monthly'] !== null): ?>
                                    <div><strong><?= e(money($p['monthly'])) ?></strong></div>
                                    <div class="muted small">/ Month</div>
                                <?php else: ?>
                                    <div class="muted small">Pricing coming soon</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="<?= e(url('store/' . $p['slug'])) ?>" class="btn btn-primary btn-block mt-2">Order Now</a>
                    </div></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="text-center small mt-2">
    <a href="<?= e(url(auth()->check() ? dashboard_path() : 'login')) ?>">← Back</a>
</div>
<?php $this->end(); ?>
