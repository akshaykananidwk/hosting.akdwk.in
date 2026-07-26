<?php /* FILE: /app/Views/layouts/client.php — client area shell */
$u = current_user() ?? ['name' => 'Client']; ?>
<!doctype html>
<html lang="<?= e(config('app.locale', 'en')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($this->yield('title', 'My Account')) ?> — <?= e(settings('general.company_name', 'AK Cloud')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">☁️ <?= e(settings('general.company_name', 'AK Cloud')) ?></div>
        <nav>
            <a href="<?= e(url('client')) ?>" class="<?= rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') === '/client' ? 'active' : '' ?>">📊 Dashboard</a>
            <a href="<?= e(url('client/services')) ?>" class="<?= is_active('/client/services') ?>">🌐 My Services</a>
            <a href="<?= e(url('client/domains')) ?>" class="<?= is_active('/client/domains') ?>">🌍 Domains</a>
            <a href="<?= e(url('client/invoices')) ?>" class="<?= is_active('/client/invoices') ?>">🧾 Invoices</a>
            <a href="<?= e(url('client/tickets')) ?>" class="<?= is_active('/client/tickets') ?>">🎫 Support</a>
            <a href="<?= e(url('client/profile')) ?>" class="<?= is_active('/client/profile') ?>">👤 Profile</a>
        </nav>
    </aside>
    <div class="content">
        <header class="topbar">
            <div class="flex items-center gap-2">
                <button class="menu-btn" aria-label="Menu">☰</button>
                <strong><?= e($this->yield('title', 'My Account')) ?></strong>
            </div>
            <div class="flex items-center gap-2">
                <button class="btn btn-sm btn-outline" onclick="toggleTheme()">🌓</button>
                <span class="avatar"><?= e(initials($u['name'] ?? 'C')) ?></span>
                <a href="<?= e(url('logout')) ?>" class="btn btn-sm btn-outline" data-confirm="Logout?">↩︎</a>
            </div>
        </header>
        <main class="main">
            <?php $this->include('partials/flash'); ?>
            <?= $this->yield('content') ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= $this->yield('scripts') ?>
</body>
</html>
