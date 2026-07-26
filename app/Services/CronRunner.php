<?php
// FILE: /app/Services/CronRunner.php
// -------------------------------------------------------------------
// MODULE 17 — Cron dispatcher + all 16 jobs. cron/cron.php એક જ entry
// થી ચાલે. દરેક job "due?" (cron expression match) હોય તો જ ચાલે,
// lock file overlap અટકાવે, દરેક job નો cron_logs માં log.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class CronRunner
{
    protected $db;

    public function __construct()
    {
        $this->db = App::instance()->make('db');
    }

    /**
     * Run every job whose schedule matches "now".
     */
    public function runDue(): array
    {
        $jobs = $this->db->table('cron_jobs')->where('is_active', 1)->get();
        $ran = [];
        foreach ($jobs as $job) {
            if (!$this->isDue($job['schedule'])) {
                continue;
            }
            $ran[] = $this->runJob($job);
        }
        return $ran;
    }

    public function runJob(array $job): array
    {
        $slug = $job['slug'];
        $start = microtime(true);
        $this->db->table('cron_jobs')->where('id', (int) $job['id'])->update([
            'last_status' => 'running', 'last_run_at' => date('Y-m-d H:i:s'),
        ]);
        $status = 'success';
        $output = '';
        try {
            $method = 'job_' . $slug;
            $output = method_exists($this, $method) ? (string) $this->{$method}() : 'no handler';
        } catch (\Throwable $e) {
            $status = 'failed';
            $output = $e->getMessage();
            App::instance()->make('logger')->error("Cron {$slug} failed: " . $e->getMessage());
        }
        $duration = (int) round((microtime(true) - $start) * 1000);
        $this->db->table('cron_jobs')->where('id', (int) $job['id'])->update(['last_status' => $status]);
        $this->db->table('cron_logs')->insert([
            'cron_job_id' => $job['id'], 'slug' => $slug, 'status' => $status,
            'output' => mb_substr($output, 0, 6000), 'duration_ms' => $duration,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['slug' => $slug, 'status' => $status, 'ms' => $duration];
    }

    // ---------------------------------------------------------------
    // Cron expression matcher (min hour dom mon dow)
    // ---------------------------------------------------------------

    public function isDue(string $expr, ?int $time = null): bool
    {
        $time ??= time();
        $parts = preg_split('/\s+/', trim($expr));
        if (count($parts) !== 5) {
            return false;
        }
        $map = [
            (int) date('i', $time), (int) date('G', $time), (int) date('j', $time),
            (int) date('n', $time), (int) date('w', $time),
        ];
        foreach ($parts as $i => $field) {
            if (!$this->fieldMatches($field, $map[$i])) {
                return false;
            }
        }
        return true;
    }

    protected function fieldMatches(string $field, int $value): bool
    {
        foreach (explode(',', $field) as $part) {
            // Step: */5 or 1-30/2
            if (str_contains($part, '/')) {
                [$range, $step] = explode('/', $part, 2);
                $step = max(1, (int) $step);
                [$min, $max] = $range === '*' ? [0, 59] : (str_contains($range, '-') ? array_map('intval', explode('-', $range)) : [(int) $range, 59]);
                for ($v = $min; $v <= $max; $v += $step) {
                    if ($v === $value) {
                        return true;
                    }
                }
                continue;
            }
            if ($part === '*') {
                return true;
            }
            if (str_contains($part, '-')) {
                [$a, $b] = array_map('intval', explode('-', $part));
                if ($value >= $a && $value <= $b) {
                    return true;
                }
                continue;
            }
            if ((int) $part === $value) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------
    // JOB HANDLERS
    // ---------------------------------------------------------------

    protected function job_provisioning_worker(): string
    {
        $jobs = $this->db->table('provisioning_queue')
            ->where('status', 'pending')
            ->where('attempts', '<', 3)
            ->limit(10)->get();
        $prov = new ProvisioningService();
        $done = 0;
        foreach ($jobs as $job) {
            $this->db->table('provisioning_queue')->where('id', (int) $job['id'])
                ->update(['status' => 'processing', 'reserved_at' => date('Y-m-d H:i:s'), 'attempts' => (int) $job['attempts'] + 1]);
            $ok = $prov->processJob($job);
            $this->db->table('provisioning_queue')->where('id', (int) $job['id'])->update([
                'status' => $ok ? 'completed' : ((int) $job['attempts'] + 1 >= 3 ? 'failed' : 'pending'),
                'completed_at' => $ok ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $done += $ok ? 1 : 0;
        }
        return "provisioned {$done}/" . count($jobs);
    }

    protected function job_whatsapp_queue_worker(): string
    {
        $wa = new WhatsAppService();
        if (!$wa->withinRateLimit()) {
            return 'rate limit reached, skipped';
        }
        $jobs = $this->db->table('whatsapp_queue')
            ->where('status', 'pending')
            ->where('attempts', '<', 3)
            ->orderBy('id', 'asc')
            ->limit(20)->get();
        // Filter scheduled-for-future in PHP (keeps builder simple).
        $sent = 0;
        foreach ($jobs as $job) {
            if (!empty($job['scheduled_at']) && strtotime($job['scheduled_at']) > time()) {
                continue;
            }
            if (!$wa->withinRateLimit()) {
                break;
            }
            $res = $wa->send($job['number'], $job['message'], $job['media_url']);
            $this->db->table('whatsapp_queue')->where('id', (int) $job['id'])->update([
                'status' => $res['ok'] ? 'sent' : 'failed',
                'attempts' => (int) $job['attempts'] + 1,
                'error' => $res['ok'] ? null : mb_substr($res['response'], 0, 500),
                'sent_at' => $res['ok'] ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $sent += $res['ok'] ? 1 : 0;
            sleep($wa->randomDelaySeconds());
        }
        return "sent {$sent}";
    }

    protected function job_email_queue_worker(): string
    {
        $mail = App::instance()->make('mail');
        $mail->setConfig($this->smtpConfig());
        $jobs = $this->db->table('email_queue')->where('status', 'pending')->where('attempts', '<', 3)->limit(20)->get();
        $sent = 0;
        foreach ($jobs as $job) {
            $ok = $mail->send($job['to_email'], $job['subject'], $job['body'], ['from_name' => $job['to_name'] ?: null]);
            $this->db->table('email_queue')->where('id', (int) $job['id'])->update([
                'status' => $ok ? 'sent' : 'failed', 'attempts' => (int) $job['attempts'] + 1,
                'sent_at' => $ok ? date('Y-m-d H:i:s') : null, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $sent += $ok ? 1 : 0;
        }
        return "emailed {$sent}";
    }

    protected function smtpConfig(): array
    {
        return [
            'host' => (string) settings('smtp.host', ''),
            'port' => (int) settings('smtp.port', 587),
            'username' => (string) settings('smtp.username', ''),
            'password' => (string) settings('smtp.password', ''),
            'encryption' => (string) settings('smtp.encryption', 'tls'),
            'from_email' => (string) settings('smtp.from_email', ''),
            'from_name' => (string) settings('smtp.from_name', settings('general.company_name', 'AK Cloud')),
        ];
    }

    protected function job_quota_check(): string
    {
        $quota = new QuotaService();
        $services = $this->db->table('services')->where('status', 'active')->limit(200)->get();
        foreach ($services as $s) {
            try {
                $quota->checkService($s);
            } catch (\Throwable) {
            }
        }
        return 'checked ' . count($services) . ' services';
    }

    protected function job_bandwidth_parser(): string
    {
        $bw = new BandwidthService();
        $services = $this->db->table('services')->where('status', 'active')->limit(200)->get();
        foreach ($services as $s) {
            try {
                $bw->parseService($s);
            } catch (\Throwable) {
            }
        }
        return 'parsed ' . count($services) . ' logs';
    }

    protected function job_server_health_check(): string
    {
        $servers = $this->db->table('servers')->where('status', '!=', 'disabled')->get();
        foreach ($servers as $server) {
            $info = AaPanelService::forServer($server)->testConnection();
            if ($info['ok']) {
                $d = $info['data'];
                $memPercent = isset($d['memRealUsed'], $d['memTotal']) && $d['memTotal'] > 0
                    ? round($d['memRealUsed'] / $d['memTotal'] * 100, 2) : null;
                $this->db->table('servers')->where('id', (int) $server['id'])->update([
                    'status' => 'online', 'last_health_at' => date('Y-m-d H:i:s'),
                    'ram_percent' => $memPercent, 'load_avg' => is_array($d['load'] ?? null) ? ($d['load']['one'] ?? '') : '',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->table('server_health_logs')->insert([
                    'server_id' => $server['id'], 'ram_percent' => $memPercent, 'status' => 'online',
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $this->db->table('servers')->where('id', (int) $server['id'])->update(['status' => 'offline', 'updated_at' => date('Y-m-d H:i:s')]);
                $phone = settings('general.admin_phone', '');
                if ($phone) {
                    (new WhatsAppService())->sendTemplate('server_down', (string) $phone, []);
                }
            }
        }
        return 'checked ' . count($servers) . ' servers';
    }

    protected function job_generate_invoices(): string
    {
        $days = (int) settings('billing.invoice_generate_days', 7);
        $cutoff = date('Y-m-d', time() + $days * 86400);
        $billing = new BillingService();
        $services = $this->db->table('services')
            ->where('status', 'active')
            ->where('next_due_date', '<=', $cutoff)
            ->whereNotNull('next_due_date')
            ->get();
        $made = 0;
        foreach ($services as $s) {
            // Skip if an unpaid invoice already covers this cycle.
            $exists = $this->db->table('invoice_items')
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->where('invoice_items.service_id', (int) $s['id'])
                ->where('invoices.status', 'unpaid')->exists();
            if ($exists) {
                continue;
            }
            if ($billing->generateRenewalInvoice($s)) {
                $made++;
            }
        }
        return "generated {$made} invoices";
    }

    protected function job_payment_reminders(): string
    {
        $wa = new WhatsAppService();
        $sent = 0;
        foreach ([7, 3, 1] as $days) {
            $target = date('Y-m-d', time() + $days * 86400);
            $invoices = $this->db->table('invoices')->where('status', 'unpaid')->where('due_date', $target)->get();
            foreach ($invoices as $inv) {
                $client = $this->db->table('clients')->where('id', (int) $inv['client_id'])->first();
                if ($client && !empty($client['phone'])) {
                    $wa->sendTemplate('reminder', $client['phone'], [
                        'client_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
                        'invoice_no' => $inv['invoice_number'], 'amount' => number_format((float) $inv['total'], 2),
                        'due_date' => $inv['due_date'],
                    ]);
                    $sent++;
                }
            }
        }
        return "reminders {$sent}";
    }

    protected function job_suspend_overdue(): string
    {
        return 'suspended ' . (new BillingService())->suspendOverdue();
    }

    protected function job_unsuspend_paid(): string
    {
        // Services suspended for overdue but whose invoices are now paid.
        $suspended = $this->db->table('services')->where('status', 'suspended')->get();
        $prov = new ProvisioningService();
        $count = 0;
        foreach ($suspended as $s) {
            if (str_contains((string) ($s['suspend_reason'] ?? ''), 'overdue')) {
                $unpaid = $this->db->table('invoice_items')
                    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                    ->where('invoice_items.service_id', (int) $s['id'])
                    ->whereIn('invoices.status', ['unpaid', 'overdue'])->exists();
                if (!$unpaid) {
                    $prov->unsuspend((int) $s['id']);
                    $count++;
                }
            }
        }
        return "unsuspended {$count}";
    }

    protected function job_ssl_auto_renew(): string
    {
        $soon = date('Y-m-d', time() + 15 * 86400);
        $details = $this->db->table('service_details')
            ->where('ssl_status', 'active')
            ->where('ssl_expires_at', '<=', $soon)
            ->whereNotNull('ssl_expires_at')->get();
        $prov = new ProvisioningService();
        $renewed = 0;
        foreach ($details as $d) {
            $service = $this->db->table('services')->where('id', (int) $d['service_id'])->first();
            if ($service && $prov->installSsl($service)) {
                $renewed++;
            }
        }
        return "ssl renewed {$renewed}";
    }

    protected function job_domain_expiry_check(): string
    {
        $wa = new WhatsAppService();
        $sent = 0;
        foreach ([30, 15, 7, 1] as $days) {
            $target = date('Y-m-d', time() + $days * 86400);
            $domains = $this->db->table('domains')->where('expiry_date', $target)->where('status', 'active')->get();
            foreach ($domains as $dom) {
                $client = $this->db->table('clients')->where('id', (int) $dom['client_id'])->first();
                if ($client && !empty($client['phone'])) {
                    $wa->sendTemplate('expiring', $client['phone'], [
                        'client_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
                        'domain' => $dom['domain'], 'expiry_date' => $dom['expiry_date'],
                        'panel_url' => url('client'),
                    ]);
                    $sent++;
                }
            }
        }
        return "domain reminders {$sent}";
    }

    protected function job_auto_backup(): string
    {
        $path = (new BackupService())->fullBackup('daily');
        return $path ? 'backup: ' . basename($path) : 'backup failed';
    }

    protected function job_cleanup_logs(): string
    {
        $cutoff = date('Y-m-d H:i:s', time() - 90 * 86400);
        $n = 0;
        foreach (['cron_logs', 'aapanel_logs', 'whatsapp_logs', 'email_logs', 'login_logs', 'api_logs'] as $t) {
            $n += $this->db->table($t)->where('created_at', '<', $cutoff)->delete();
        }
        return "cleaned {$n} log rows";
    }

    protected function job_github_update_check(): string
    {
        if ((string) settings('updates.auto_check', '1') !== '1') {
            return 'auto-check off';
        }
        $check = (new UpdateService())->checkForUpdate();
        if (!empty($check['available'])) {
            $this->db->table('notifications')->insert([
                'type' => 'update', 'title' => 'નવું update ઉપલબ્ધ છે',
                'body' => ($check['message'] ?? ''), 'link' => '/admin/updates', 'icon' => '🔄',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return 'update available';
        }
        return 'up to date';
    }

    protected function job_daily_summary_whatsapp(): string
    {
        $today = date('Y-m-d');
        $revenue = $this->db->table('transactions')
            ->where('type', 'payment')->where('status', 'success')
            ->where('created_at', '>=', $today . ' 00:00:00')->sum('amount');
        $orders = $this->db->table('orders')->where('created_at', '>=', $today . ' 00:00:00')->count();
        $phone = settings('general.admin_phone', '');
        if ($phone) {
            (new WhatsAppService())->send((string) $phone,
                "📊 આજનો સારાંશ ({$today})\nનવા ઓર્ડર: {$orders}\nકલેક્શન: ₹" . number_format($revenue, 2));
        }
        return "revenue ₹{$revenue}, orders {$orders}";
    }
}
