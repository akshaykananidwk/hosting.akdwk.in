<?php /* FILE: /app/Views/errors/403.php */
$message = $message ?? ''; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 — Access denied</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><div class="auth-card text-center">
<h1 style="font-size:3rem">403</h1>
<h2>Access denied</h2>
<p class="muted"><?= e($message !== '' ? $message : 'You do not have permission to view this page.') ?></p>
<a href="<?= e(url('/')) ?>" class="btn btn-primary mt-2">Back to home</a>
</div></div></body></html>
