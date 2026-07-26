<?php /* FILE: /app/Views/layouts/admin.php — admin panel shell (sidebar + topbar) */
$u = current_user() ?? ['name' => 'Admin']; ?>
<!doctype html>
<html lang="<?= e(config('app.locale', 'gu')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($this->yield('title', 'Admin')) ?> — <?= e(settings('general.company_name', 'AK Cloud')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">☁️ <?= e(settings('general.company_name', 'AK Cloud')) ?></div>
        <nav>
            <a href="<?= e(url('admin')) ?>" class="<?= is_active('/admin', '') === '' && rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') === '/admin' ? 'active' : '' ?>">📊 Dashboard</a>
            <div class="group-label">Business</div>
            <a href="<?= e(url('admin/clients')) ?>" class="<?= is_active('/admin/clients') ?>">👥 Clients</a>
            <a href="<?= e(url('admin/services')) ?>" class="<?= is_active('/admin/services') ?>">🌐 Services</a>
            <a href="<?= e(url('admin/invoices')) ?>" class="<?= is_active('/admin/invoices') ?>">🧾 Invoices</a>
            <a href="<?= e(url('admin/transactions')) ?>" class="<?= is_active('/admin/transactions') ?>">💳 Transactions</a>
            <a href="<?= e(url('admin/tickets')) ?>" class="<?= is_active('/admin/tickets') ?>">🎫 Tickets</a>
            <div class="group-label">Catalog</div>
            <a href="<?= e(url('admin/products')) ?>" class="<?= is_active('/admin/products') ?>">📦 Products</a>
            <a href="<?= e(url('admin/coupons')) ?>" class="<?= is_active('/admin/coupons') ?>">🏷️ Coupons</a>
            <a href="<?= e(url('admin/domains')) ?>" class="<?= is_active('/admin/domains') ?>">🌍 Domains</a>
            <div class="group-label">Infrastructure</div>
            <a href="<?= e(url('admin/servers')) ?>" class="<?= is_active('/admin/servers') ?>">🖥️ Servers</a>
            <a href="<?= e(url('admin/provisioning')) ?>" class="<?= is_active('/admin/provisioning') ?>">⚙️ Provisioning</a>
            <a href="<?= e(url('admin/backups')) ?>" class="<?= is_active('/admin/backups') ?>">💾 Backups</a>
            <div class="group-label">System</div>
            <a href="<?= e(url('admin/reports')) ?>" class="<?= is_active('/admin/reports') ?>">📈 Reports</a>
            <a href="<?= e(url('admin/templates')) ?>" class="<?= is_active('/admin/templates') ?>">✉️ Templates</a>
            <a href="<?= e(url('admin/settings')) ?>" class="<?= is_active('/admin/settings') ?>">⚙️ Settings</a>
            <a href="<?= e(url('admin/updates')) ?>" class="<?= is_active('/admin/updates') ?>">🔄 Updates</a>
            <a href="<?= e(url('admin/logs')) ?>" class="<?= is_active('/admin/logs') ?>">📜 Logs</a>
        </nav>
    </aside>
    <div class="content">
        <header class="topbar">
            <div class="flex items-center gap-2">
                <button class="menu-btn" aria-label="Menu">☰</button>
                <strong><?= e($this->yield('title', 'Admin')) ?></strong>
            </div>
            <div class="flex items-center gap-2">
                <button class="btn btn-sm btn-outline" onclick="toggleTheme()">🌓</button>
                <span class="avatar"><?= e(initials($u['name'] ?? 'A')) ?></span>
                <a href="<?= e(url('logout')) ?>" class="btn btn-sm btn-outline" data-confirm="Logout કરવું છે?">↩︎ Logout</a>
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
