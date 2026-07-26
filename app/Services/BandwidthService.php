<?php
// FILE: /app/Services/BandwidthService.php
// -------------------------------------------------------------------
// MODULE 5(b) — Per-site bandwidth tracking (not offered by the aaPanel API).
// Incrementally parses the nginx access log (/www/wwwlogs/{domain}.log),
// remembering offset + inode so a rotated log restarts cleanly.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class BandwidthService
{
    protected string $logDir = '/www/wwwlogs';

    /**
     * Parse today's new log lines for a service and add bytes to the
     * date-wise counter. Returns bytes added this run.
     */
    public function parseService(array $service): int
    {
        $db = App::instance()->make('db');
        $domain = $service['domain'];
        $logFile = $this->logDir . '/' . $domain . '.log';
        if (!@is_file($logFile)) {
            return 0;
        }

        $today = date('Y-m-d');
        $row = $db->table('bandwidth_usage')
            ->where('service_id', (int) $service['id'])
            ->where('usage_date', $today)
            ->first();

        $stat = @stat($logFile);
        $inode = $stat['ino'] ?? null;
        $offset = (int) ($row['log_offset'] ?? 0);
        $prevInode = $row['log_inode'] ?? null;

        // Log rotated (inode changed) or truncated → restart from 0.
        if ($prevInode !== null && (string) $prevInode !== (string) $inode) {
            $offset = 0;
        }
        $fileSize = (int) ($stat['size'] ?? 0);
        if ($offset > $fileSize) {
            $offset = 0;
        }

        $bytes = 0;
        $handle = @fopen($logFile, 'rb');
        if ($handle === false) {
            return 0;
        }
        if ($offset > 0) {
            fseek($handle, $offset);
        }
        while (($line = fgets($handle)) !== false) {
            $bytes += $this->extractBytes($line);
        }
        $newOffset = ftell($handle);
        fclose($handle);

        $totalToday = (int) ($row['bytes'] ?? 0) + $bytes;

        if ($row) {
            $db->table('bandwidth_usage')->where('id', (int) $row['id'])->update([
                'bytes' => $totalToday,
                'log_offset' => $newOffset,
                'log_inode' => $inode,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('bandwidth_usage')->insert([
                'tenant_id' => $service['tenant_id'],
                'service_id' => $service['id'],
                'usage_date' => $today,
                'bytes' => $totalToday,
                'log_offset' => $newOffset,
                'log_inode' => $inode,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->updateMonthlyAndEnforce($service);
        return $bytes;
    }

    /**
     * nginx "combined" log — field 10 (after the quoted request) is
     * body_bytes_sent. Robustly grab the bytes token.
     */
    protected function extractBytes(string $line): int
    {
        // Format: ip - - [date] "GET / HTTP/1.1" 200 12345 "ref" "ua"
        if (preg_match('/"\s+\d{3}\s+(\d+)\s/', $line, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    protected function updateMonthlyAndEnforce(array $service): void
    {
        $db = App::instance()->make('db');
        $monthStart = date('Y-m-01');
        $row = $db->selectOne(
            'SELECT COALESCE(SUM(bytes),0) AS total FROM bandwidth_usage
              WHERE service_id = ? AND usage_date >= ?',
            [(int) $service['id'], $monthStart]
        );
        $usedMb = (int) round(((int) ($row['total'] ?? 0)) / 1024 / 1024);
        $db->table('services')->where('id', (int) $service['id'])->update([
            'bandwidth_used_mb' => $usedMb,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $limit = (int) ($service['bandwidth_limit_mb'] ?? 0);
        if ((int) ($service['is_bw_unlimited'] ?? 0) === 1 || $limit <= 0) {
            return;
        }
        $percent = round($usedMb / $limit * 100, 2);

        if ($percent >= 80 && $percent < 100) {
            $this->warn($service, 'bandwidth', 80);
        }
        if ($percent >= 100 && ($service['status'] ?? '') === 'active') {
            $this->warn($service, 'bandwidth', 100);
            (new ProvisioningService())->suspend((int) $service['id'], 'Bandwidth limit exceeded');
        }
    }

    protected function warn(array $service, string $type, int $threshold): void
    {
        $db = App::instance()->make('db');
        $exists = $db->table('quota_warnings')
            ->where('service_id', (int) $service['id'])
            ->where('type', $type)
            ->where('threshold', (string) $threshold)
            ->where('resolved', 0)
            ->first();
        if ($exists) {
            return;
        }
        $db->table('quota_warnings')->insert([
            'tenant_id' => $service['tenant_id'],
            'service_id' => $service['id'],
            'type' => $type,
            'threshold' => (string) $threshold,
            'notified_at' => date('Y-m-d H:i:s'),
            'resolved' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $client = $db->table('clients')->where('id', (int) $service['client_id'])->first();
        if ($client && !empty($client['phone'])) {
            (new WhatsAppService())->sendTemplate('bandwidth_warning', $client['phone'], [
                'client_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
                'domain' => $service['domain'],
                'company_name' => settings('general.company_name', 'AK Cloud'),
            ]);
        }
    }
}
