<?php
// FILE: /cron/cron.php
// -------------------------------------------------------------------
// MODULE 17 — single cron entry. crontab:
//   */5 * * * * /www/server/php/82/bin/php /path/cron/cron.php >/dev/null 2>&1
// Lock file overlap અટકાવે; CronRunner due jobs ચલાવે.
// -------------------------------------------------------------------

declare(strict_types=1);

$base = dirname(__DIR__);

require $base . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($base);
require $base . '/app/Helpers/functions.php';

// Boot without HTTP session.
$app = \App\Core\App::boot($base);

if (!$app->isInstalled()) {
    fwrite(STDERR, "AK Cloud not installed yet.\n");
    exit(1);
}

// Overlap lock.
$lockFile = $app->storagePath('temp/cron.lock');
@mkdir(dirname($lockFile), 0775, true);
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Cron already running — skipped.\n");
    exit(0);
}

$startedAt = microtime(true);
try {
    $runner = new \App\Services\CronRunner();
    $ran = $runner->runDue();
    foreach ($ran as $r) {
        echo sprintf("[%s] %s — %s (%dms)\n", date('H:i:s'), $r['slug'], $r['status'], $r['ms']);
    }
    if (!$ran) {
        echo "No jobs due at " . date('H:i') . "\n";
    }
} catch (\Throwable $e) {
    $app->make('logger')->exception($e, 'cron');
    fwrite(STDERR, 'Cron error: ' . $e->getMessage() . "\n");
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

echo sprintf("Done in %dms\n", (int) round((microtime(true) - $startedAt) * 1000));
