<?php /* FILE: /app/Views/reseller/pricing.php — reseller pricing overrides + markup */ ?>
<?php $this->extend('layouts/admin'); $this->set('title', __('pricing')); ?>
<?php $this->section('content'); ?>

<?php
/** @var array<string,mixed>|null $reseller */
/** @var array<int,array{product:array,base:?array,override:?array}> $rows */
$markupType  = $reseller['markup_type'] ?? 'percent';
$markupValue = $reseller['markup_value'] ?? '0.00';

$cycles = [
    'monthly'     => __('monthly'),
    'quarterly'   => __('quarterly'),
    'half_yearly' => __('half_yearly'),
    'yearly'      => __('yearly'),
];
?>

<form method="post" action="<?= e(url('reseller/pricing')) ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-head"><span><?= e(__('markup')) ?></span></div>
        <div class="card-body">
            <div class="grid cols-2">
                <div class="form-group">
                    <label><?= e(__('markup_type')) ?></label>
                    <select name="markup_type">
                        <option value="percent" <?= $markupType === 'percent' ? 'selected' : '' ?>><?= e(__('percent')) ?></option>
                        <option value="fixed" <?= $markupType === 'fixed' ? 'selected' : '' ?>><?= e(__('fixed')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= e(__('markup_value')) ?></label>
                    <input type="number" step="0.01" min="0" name="markup_value" value="<?= e($markupValue) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <span><?= e(__('pricing')) ?></span>
            <button type="submit" class="btn btn-sm btn-primary"><?= e(__('save')) ?></button>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= e(__('product')) ?></th>
                            <?php foreach ($cycles as $label): ?>
                                <th><?= e($label) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="5" class="muted"><?= e(__('no_records')) ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php $p = $row['product']; $pid = (int) $p['id']; $base = $row['base']; $ov = $row['override']; ?>
                                <tr>
                                    <td>
                                        <strong><?= e($p['name']) ?></strong>
                                    </td>
                                    <?php foreach (array_keys($cycles) as $cycle): ?>
                                        <?php
                                        $baseVal = $base[$cycle] ?? null;
                                        $ovVal   = $ov[$cycle] ?? null;
                                        ?>
                                        <td>
                                            <input
                                                type="number" step="0.01" min="0"
                                                name="<?= e($cycle) ?>[<?= $pid ?>]"
                                                value="<?= e($ovVal !== null ? $ovVal : '') ?>"
                                                placeholder="<?= e($baseVal !== null ? money($baseVal) : '—') ?>">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted mt-3 small">Placeholder = base price. ખાલી છોડો તો base price વપરાશે.</p>
        </div>
    </div>
</form>

<?php $this->end(); ?>
