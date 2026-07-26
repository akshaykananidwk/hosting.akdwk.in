<?php /* FILE: /app/Views/errors/403.php */
$message = $message ?? ''; ?>
<!doctype html>
<html lang="gu"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 — પ્રવેશ નથી</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><div class="auth-card text-center">
<h1 style="font-size:3rem">403</h1>
<h2>પ્રવેશ નથી</h2>
<p class="muted"><?= e($message !== '' ? $message : 'આ પેજ જોવાની તમને પરવાનગી નથી.') ?></p>
<a href="<?= e(url('/')) ?>" class="btn btn-primary mt-2">હોમ પર જાઓ</a>
</div></div></body></html>
