<?php
// FILE: /app/Services/BackupService.php
// -------------------------------------------------------------------
// MODULE 19 — Backup & restore. Full app ZIP + PHP-based DB dump
// (works without mysqldump), integrity verify, retention. Per-client
// site backups use the aaPanel ToBackup API.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;
use ZipArchive;

class BackupService
{
    protected string $backupDir;

    public function __construct()
    {
        $this->backupDir = App::instance()->storagePath('backups');
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0775, true);
        }
    }

    /**
     * Full system backup: files (excluding storage/vendor) + DB dump.
     * Returns the zip path or null on failure.
     */
    public function fullBackup(?string $label = null): ?string
    {
        $stamp = date('Ymd_His');
        $name = 'backup_' . $stamp . ($label ? '_' . slugify($label) : '') . '.zip';
        $zipPath = $this->backupDir . '/' . $name;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        // 1) DB dump into the archive.
        $dump = $this->dumpDatabase();
        $zip->addFromString('database.sql', $dump);

        // 2) Application files (skip heavy/regenerable dirs).
        $base = App::instance()->basePath();
        $skip = ['/.git', '/storage/backups', '/storage/cache', '/storage/temp', '/vendor', '/node_modules'];
        $this->addDirToZip($zip, $base, $base, $skip);

        $zip->close();

        // 3) Integrity verify.
        if (!$this->verifyZip($zipPath)) {
            @unlink($zipPath);
            return null;
        }

        $this->record('full', $name, $zipPath, filesize($zipPath) ?: 0);
        $this->applyRetention();
        return $zipPath;
    }

    /**
     * PHP-based mysqldump replacement — schema + data as INSERTs.
     */
    public function dumpDatabase(): string
    {
        $db = App::instance()->make('db');
        $pdo = $db->pdo();
        $out = "-- AK Cloud DB dump " . date('Y-m-d H:i:s') . "\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_ASSOC);
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n" . ($create['Create Table'] ?? '') . ";\n\n";

            $rows = $pdo->query('SELECT * FROM `' . $table . '`');
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $cols = array_map(fn($c) => '`' . $c . '`', array_keys($row));
                $vals = array_map(function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote((string) $v);
                }, array_values($row));
                $out .= "INSERT INTO `{$table}` (" . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
            $out .= "\n";
        }
        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }

    protected function addDirToZip(ZipArchive $zip, string $dir, string $base, array $skip): void
    {
        $relBase = str_replace($base, '', $dir);
        foreach ($skip as $s) {
            if (str_starts_with($relBase . '/', $s . '/') || $relBase === $s) {
                return;
            }
        }
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            $rel = ltrim(str_replace($base, '', $path), '/');
            $skipThis = false;
            foreach ($skip as $s) {
                if (str_starts_with('/' . $rel, $s . '/') || '/' . $rel === $s) {
                    $skipThis = true;
                    break;
                }
            }
            if ($skipThis) {
                continue;
            }
            if (is_dir($path)) {
                $zip->addEmptyDir($rel);
                $this->addDirToZip($zip, $path, $base, $skip);
            } elseif (is_file($path)) {
                $zip->addFile($path, $rel);
            }
        }
    }

    public function verifyZip(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            return false;
        }
        $ok = $zip->numFiles > 0;
        $zip->close();
        return $ok;
    }

    protected function record(string $type, string $filename, string $path, int $size): void
    {
        App::instance()->make('db')->table('backups')->insert([
            'tenant_id' => null, 'type' => $type, 'destination' => 'local',
            'filename' => $filename, 'path' => $path, 'size' => $size,
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Keep the newest N backups (default 10).
     */
    public function applyRetention(int $keep = 10): void
    {
        $files = glob($this->backupDir . '/backup_*.zip') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    /**
     * Trigger a per-site backup on the aaPanel server (Module 19).
     */
    public function backupClientSite(array $service): bool
    {
        if (!$service['server_id'] || !$service['aapanel_site_id']) {
            return false;
        }
        $server = App::instance()->make('db')->table('servers')->where('id', (int) $service['server_id'])->first();
        if (!$server) {
            return false;
        }
        $res = AaPanelService::forServer($server)->backupSite((int) $service['aapanel_site_id']);
        return (bool) ($res['status'] ?? false);
    }
}
