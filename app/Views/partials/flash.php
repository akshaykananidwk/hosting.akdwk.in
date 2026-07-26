<?php /* FILE: /app/Views/partials/flash.php — flash messages + validation errors */ ?>
<?php foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info', 'warning' => 'warning'] as $key => $type): ?>
    <?php if (\App\Core\Session::has($key)): ?>
        <div class="alert alert-<?= $type ?>" data-auto><?= e(\App\Core\Session::get($key)) ?></div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (\App\Core\Session::has('errors')): $errs = \App\Core\Session::get('errors'); ?>
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:18px">
            <?php foreach ((array) $errs as $field => $messages): ?>
                <?php foreach ((array) $messages as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
