<?php
// FILE: /app/Services/UpdateService.php
// -------------------------------------------------------------------
// MODULE 18 — GitHub auto-update. Check → backup → download zipball →
// extract → protected-files skip → copy → migrate → cache clear →
// version bump. If any step fails -> AUTOMATIC ROLLBACK.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;
use ZipArchive;

class UpdateService
{
    protected string $base;
    protected array $protected = [
        '/config/config.php', '/.env', '/install/installed.lock',
        '/public/uploads', '/storage', '/custom', '/.htaccess',
    ];

    public function __construct()
    {
        $this->base = App::instance()->basePath();
        $extra = (array) json_decode((string) settings('updates.protected_files', '[]'), true);
        $this->protected = array_values(array_unique(array_merge($this->protected, $extra)));
    }

    public function currentVersion(): string
    {
        $json = json_decode((string) @file_get_contents($this->base . '/version.json'), true) ?: [];
        return (string) ($json['version'] ?? '0.0.0');
    }

    protected function repo(): string
    {
        return (string) settings('updates.repo', config('app.repository', ''));
    }

    protected function branch(): string
    {
        return (string) settings('updates.branch', 'main');
    }

    protected function token(): string
    {
        return (string) settings('updates.github_token', '');
    }

    protected function apiGet(string $url): ?array
    {
        $ch = curl_init($url);
        $headers = ['User-Agent: AKCloud-Updater', 'Accept: application/vnd.github+json'];
        if ($this->token() !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300 && is_string($resp)) {
            return json_decode($resp, true);
        }
        return null;
    }

    /**
     * Check GitHub for the latest commit on the branch.
     */
    public function checkForUpdate(): array
    {
        $repo = $this->repo();
        if ($repo === '') {
            return ['available' => false, 'error' => 'No repository configured.'];
        }
        $commit = $this->apiGet("https://api.github.com/repos/{$repo}/commits/{$this->branch()}");
        if (!$commit) {
            return ['available' => false, 'error' => 'Could not connect to GitHub (check the token and repository).'];
        }
        $latestSha = substr($commit['sha'] ?? '', 0, 7);
        $localSha = (string) settings('updates.current_sha', '');

        return [
            'available' => $latestSha !== '' && $latestSha !== $localSha,
            'latest_sha' => $latestSha,
            'local_sha' => $localSha,
            'message' => $commit['commit']['message'] ?? '',
            'author' => $commit['commit']['author']['name'] ?? '',
            'date' => $commit['commit']['author']['date'] ?? '',
            'current_version' => $this->currentVersion(),
        ];
    }

    /**
     * Run the full update. $progress is an optional callback(step, pct).
     * Returns ['ok'=>bool,'message'=>string].
     */
    public function runUpdate(?callable $progress = null): array
    {
        $log = [];
        $report = function (string $step, int $pct) use (&$log, $progress) {
            $log[] = $step;
            if ($progress) {
                $progress($step, $pct);
            }
        };

        ignore_user_abort(true);
        @set_time_limit(0);

        $fromVersion = $this->currentVersion();
        $backupPath = null;
        try {
            // 1) Maintenance ON + preflight.
            settings()->set('general.maintenance_mode', '1');
            $report('Maintenance mode enabled', 5);
            if (disk_free_space($this->base) < 200 * 1024 * 1024) {
                throw new \RuntimeException('Not enough disk space (200MB required).');
            }

            // 2) Auto backup.
            $report('Creating backup…', 15);
            $backupPath = (new BackupService())->fullBackup('pre-update');
            if (!$backupPath) {
                throw new \RuntimeException('Backup failed — update aborted.');
            }

            // 3) Download zipball.
            $report('Downloading from GitHub…', 35);
            $zipFile = $this->download();

            // 4) Extract.
            $report('Extract…', 55);
            $extractDir = $this->extract($zipFile);

            // 5) Copy (skip protected).
            $report('Copying files (protected files preserved)…', 70);
            $this->copyFiles($extractDir);

            // 6) Migrations.
            $report('Database migrations…', 85);
            $this->runMigrations();

            // 7) Cache clear.
            $report('Cache clear…', 92);
            App::instance()->make('cache')->flush();
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            // 8) Version bump + history.
            $check = $this->checkForUpdate();
            settings()->set('updates.current_sha', $check['latest_sha'] ?? '');
            $this->recordHistory($fromVersion, $this->currentVersion(), $check['latest_sha'] ?? '', 'success', $backupPath, implode("\n", $log));

            // 9) Maintenance OFF + health.
            settings()->set('general.maintenance_mode', '0');
            $report('Complete ✅', 100);

            // Notify admin.
            $adminPhone = settings('general.admin_phone', '');
            if ($adminPhone) {
                (new WhatsAppService())->send((string) $adminPhone, "✅ AK Cloud update successful. Version: " . $this->currentVersion());
            }
            @unlink($zipFile);
            $this->rrmdir($extractDir);
            return ['ok' => true, 'message' => 'Update completed successfully.'];

        } catch (\Throwable $e) {
            // AUTO ROLLBACK.
            $this->rollback($backupPath);
            settings()->set('general.maintenance_mode', '0');
            $this->recordHistory($fromVersion, $fromVersion, '', 'rolled_back', $backupPath, $e->getMessage());
            $adminPhone = settings('general.admin_phone', '');
            if ($adminPhone) {
                (new WhatsAppService())->send((string) $adminPhone, '❌ AK Cloud update failed — the system was safely rolled back to the previous version. ' . $e->getMessage());
            }
            return ['ok' => false, 'message' => 'Update failed and was rolled back: ' . $e->getMessage()];
        }
    }

    protected function download(): string
    {
        $repo = $this->repo();
        $url = "https://api.github.com/repos/{$repo}/zipball/{$this->branch()}";
        $tmp = App::instance()->storagePath('temp/update_' . date('Ymd_His') . '.zip');
        $fp = fopen($tmp, 'wb');
        $ch = curl_init($url);
        $headers = ['User-Agent: AKCloud-Updater'];
        if ($this->token() !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers, CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $code >= 300) {
            throw new \RuntimeException('Download failed (HTTP ' . $code . ').');
        }
        return $tmp;
    }

    protected function extract(string $zipFile): string
    {
        $dir = App::instance()->storagePath('temp/update_extract_' . date('Ymd_His'));
        @mkdir($dir, 0775, true);
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new \RuntimeException('ZIP invalid.');
        }
        $zip->extractTo($dir);
        $zip->close();
        // GitHub wraps everything in a single top-level folder.
        $items = array_values(array_filter(scandir($dir) ?: [], fn($i) => $i !== '.' && $i !== '..'));
        if (count($items) === 1 && is_dir($dir . '/' . $items[0])) {
            return $dir . '/' . $items[0];
        }
        return $dir;
    }

    protected function isProtected(string $rel): bool
    {
        $rel = '/' . ltrim($rel, '/');
        foreach ($this->protected as $p) {
            if ($rel === $p || str_starts_with($rel, rtrim($p, '/') . '/')) {
                return true;
            }
        }
        return false;
    }

    protected function copyFiles(string $src): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $rel = ltrim(str_replace($src, '', $item->getPathname()), '/');
            if ($this->isProtected($rel)) {
                continue;
            }
            $dest = $this->base . '/' . $rel;
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    @mkdir($dest, 0775, true);
                }
            } else {
                @copy($item->getPathname(), $dest);
            }
        }
    }

    protected function runMigrations(): void
    {
        $db = App::instance()->make('db');
        $applied = $db->table('migrations')->pluck('migration');
        $files = glob($this->base . '/database/migrations/*.sql') ?: [];
        sort($files);
        $batch = ((int) ($db->table('migrations')->orderBy('batch', 'desc')->value('batch') ?? 0)) + 1;
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            $db->pdo()->exec($sql);
            $db->table('migrations')->insert([
                'migration' => $name, 'batch' => $batch, 'applied_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    protected function rollback(?string $backupPath): void
    {
        if (!$backupPath || !is_file($backupPath)) {
            return;
        }
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== true) {
            return;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === 'database.sql' || $this->isProtected($name)) {
                continue;
            }
            $dest = $this->base . '/' . $name;
            if (str_ends_with($name, '/')) {
                if (!is_dir($dest)) {
                    @mkdir($dest, 0775, true);
                }
                continue;
            }
            $stream = $zip->getStream($name);
            if ($stream) {
                @file_put_contents($dest, stream_get_contents($stream));
                fclose($stream);
            }
        }
        $zip->close();
    }

    protected function recordHistory(string $from, string $to, string $sha, string $status, ?string $backup, string $log): void
    {
        App::instance()->make('db')->table('update_history')->insert([
            'from_version' => $from, 'to_version' => $to, 'commit_hash' => $sha,
            'status' => $status, 'backup_path' => $backup, 'log' => mb_substr($log, 0, 60000),
            'admin_id' => auth()->id(),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
