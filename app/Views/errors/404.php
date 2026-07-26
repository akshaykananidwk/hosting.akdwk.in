<?php /* FILE: /app/Views/errors/404.php */ ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 — Page not found</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><div class="auth-card text-center">
<h1 style="font-size:3rem">404</h1>
<p class="muted">This page could not be found.</p>
<a href="<?= e(url('/')) ?>" class="btn btn-primary mt-2">Back to home</a>
</div></div></body></html>
