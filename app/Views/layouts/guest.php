<?php /* FILE: /app/Views/layouts/guest.php — auth/public pages layout */ ?>
<!doctype html>
<html lang="<?= e(config('app.locale', 'en')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($this->yield('title', settings('general.company_name', 'AK Cloud'))) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="text-center mb-3">
                <h1 style="font-size:1.6rem">☁️ <?= e(settings('general.company_name', 'AK Cloud')) ?></h1>
                <p class="muted small">aaPanel-powered Hosting</p>
            </div>
            <div class="card">
                <div class="card-body">
                    <?php $this->include('partials/flash'); ?>
                    <?= $this->yield('content') ?>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
