<?php
// FILE: /app/Services/QuotaService.php
// -------------------------------------------------------------------
// MODULE 5(a) — Disk quota enforcement (aaPanel પોતે નથી આપતું).
// du -sb થી site size + information_schema થી DB size ગણે, plan
// limit સાથે સરખાવે, 80/90/100% warnings મોકલે, grace પછી suspend.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class QuotaService
{
    /**
     * Measure and record disk usage for one service; returns percent used.
     */
    public function checkService(array $service): float
    {
        $db = App::instance()->make('db');

        $files = $this->directorySize($service['site_path'] ?? '');
        $dbBytes = $this->databaseSize($service);
        $total = $files + $dbBytes;

        $limitBytes = ((int) ($service['disk_limit_mb'] ?? 0)) * 1024 * 1024;
        $percent = $limitBytes > 0 ? round($total / $limitBytes * 100, 2) : 0.0;

        $db->table('disk_usage')->insert([
            'tenant_id' => $service['tenant_id'],
            'service_id' => $service['id'],
            'files_bytes' => $files,
            'db_bytes' => $dbBytes,
            'total_bytes' => $total,
            'percent' => $percent,
            'checked_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->table('services')->where('id', (int) $service['id'])->update([
            'disk_used_mb' => (int) round($total / 1024 / 1024),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->handleThresholds($service, $percent);
        return $percent;
    }

    /**
     * du -sb {path} — escapeshellarg ફરજિયાત (command injection guard).
     * shell_exec disabled હોય તો PHP recursive fallback.
     */
    public function directorySize(string $path): int
    {
        if ($path === '' || !@is_dir($path)) {
            return 0;
        }
        if (function_exists('shell_exec') && !$this->isDisabled('shell_exec')) {
            $out = @shell_exec('du -sb ' . escapeshellarg($path) . ' 2>/dev/null');
            if (is_string($out) && preg_match('/^(\d+)/', trim($out), $m)) {
                return (int) $m[1];
            }
        }
        return $this->phpRecursiveSize($path);
    }

    protected function phpRecursiveSize(string $path): int
    {
        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $size += $file->getSize();
            }
        } catch (\Throwable) {
            return 0;
        }
        return $size;
    }

    /**
     * Sum data+index length from information_schema for the service DB.
     */
    public function databaseSize(array $service): int
    {
        $detail = App::instance()->make('db')->table('service_details')
            ->where('service_id', (int) $service['id'])->first();
        $dbName = $detail['db_name'] ?? null;
        if (!$dbName) {
            return 0;
        }
        $row = App::instance()->make('db')->selectOne(
            'SELECT COALESCE(SUM(data_length + index_length),0) AS bytes
               FROM information_schema.TABLES WHERE table_schema = ?',
            [$dbName]
        );
        return (int) ($row['bytes'] ?? 0);
    }

    /**
     * Fire 80/90/100% warnings and auto-suspend after grace.
     */
    protected function handleThresholds(array $service, float $percent): void
    {
        $db = App::instance()->make('db');
        $thresholds = array_map('intval', explode(',', (string) settings('quota.warn_thresholds', '80,90,100')));

        foreach ($thresholds as $t) {
            if ($percent < $t) {
                continue;
            }
            $exists = $db->table('quota_warnings')
                ->where('service_id', (int) $service['id'])
                ->where('type', 'disk')
                ->where('threshold', (string) $t)
                ->where('resolved', 0)
                ->first();
            if ($exists) {
                continue;
            }
            $graceUntil = $t >= 100 ? date('Y-m-d', time() + (int) settings('quota.disk_grace_days', 3) * 86400) : null;
            $db->table('quota_warnings')->insert([
                'tenant_id' => $service['tenant_id'],
                'service_id' => $service['id'],
                'type' => 'disk',
                'threshold' => (string) $t,
                'notified_at' => date('Y-m-d H:i:s'),
                'grace_until' => $graceUntil,
                'resolved' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->notify($service, $t, $percent);
        }

        // Enforce suspension when the 100% grace period has passed.
        $over = $db->table('quota_warnings')
            ->where('service_id', (int) $service['id'])
            ->where('type', 'disk')
            ->where('threshold', '100')
            ->where('resolved', 0)
            ->whereNotNull('grace_until')
            ->first();
        if ($over && $over['grace_until'] < date('Y-m-d') && ($service['status'] ?? '') === 'active') {
            (new ProvisioningService())->suspend((int) $service['id'], 'Disk quota exceeded');
        }
    }

    protected function notify(array $service, int $threshold, float $percent): void
    {
        $client = App::instance()->make('db')->table('clients')->where('id', (int) $service['client_id'])->first();
        if (!$client || empty($client['phone'])) {
            return;
        }
        (new WhatsAppService())->sendTemplate('disk_warning', $client['phone'], [
            'client_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
            'domain' => $service['domain'],
            'disk_used' => format_mb((int) round(($service['disk_limit_mb'] ?? 0) * $percent / 100)),
            'disk_limit' => format_mb((int) ($service['disk_limit_mb'] ?? 0)),
            'company_name' => settings('general.company_name', 'AK Cloud'),
        ]);
    }

    protected function isDisabled(string $func): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return in_array($func, $disabled, true);
    }
}
