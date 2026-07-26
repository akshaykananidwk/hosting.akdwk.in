<?php
// FILE: /tools/migrate.php
// -------------------------------------------------------------------
// Apply pending SQL migrations from /database/migrations to the live
// database. Each file runs once and is recorded in the `migrations`
// table. Run after `git pull`:
//
//     php tools/migrate.php
//
// The GitHub auto-updater (Module 18) runs the same migrations during
// an update; this script is for manual pulls.
// -------------------------------------------------------------------

declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($base);
require $base . '/app/Helpers/functions.php';

$app = \App\Core\App::boot($base);

if (!$app->isInstalled()) {
    fwrite(STDERR, "AK Cloud is not installed yet — run /install first.\n");
    exit(1);
}

$db = $app->make('db');
if (!$db->ping()) {
    fwrite(STDERR, "Cannot connect to the database. Check config/config.php.\n");
    exit(1);
}

// Ensure the tracking table exists (older installs may predate it).
$db->statement(
    'CREATE TABLE IF NOT EXISTS `migrations` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(255) NOT NULL,
        `batch` INT NOT NULL DEFAULT 1,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $db->table('migrations')->pluck('migration');
$files = glob($base . '/database/migrations/*.sql') ?: [];
sort($files);

$batch = ((int) ($db->table('migrations')->orderBy('batch', 'desc')->value('batch') ?? 0)) + 1;
$ran = 0;

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    $sql = (string) file_get_contents($file);
    echo "→ {$name} ... ";
    try {
        $db->pdo()->exec($sql);
        $db->table('migrations')->insert([
            'migration' => $name,
            'batch' => $batch,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
        echo "done\n";
        $ran++;
    } catch (\Throwable $e) {
        echo "FAILED\n";
        fwrite(STDERR, "  {$e->getMessage()}\n");
        exit(1);
    }
}

// Settings are cached in files — clear so new values are picked up.
$app->make('cache')->flush();

echo $ran === 0
    ? "Nothing to migrate — database is up to date.\n"
    : "Applied {$ran} migration(s). Cache cleared.\n";
