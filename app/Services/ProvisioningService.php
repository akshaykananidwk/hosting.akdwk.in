<?php
// FILE: /app/Services/ProvisioningService.php
// -------------------------------------------------------------------
// MODULE 7 — Order → live site orchestration. aaPanel createSite + DB
// + FTP + SSL + isolation + quota. Every step is written to provisioning_logs.
// On failure it rolls back (deletes the created site/db/ftp); on success it notifies by WhatsApp + email.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class ProvisioningService
{
    protected $db;

    public function __construct()
    {
        $this->db = App::instance()->make('db');
    }

    // ---------------------------------------------------------------
    // Server auto-assignment (least load + most free disk)
    // ---------------------------------------------------------------

    public function assignServer(?int $preferredServerId = null): ?array
    {
        if ($preferredServerId) {
            $s = $this->db->table('servers')->where('id', $preferredServerId)->where('status', 'online')->first();
            if ($s) {
                return $s;
            }
        }
        return $this->db->table('servers')
            ->where('status', 'online')
            ->where('auto_assign', 1)
            ->orderBy('disk_percent', 'asc')
            ->orderBy('active_accounts', 'asc')
            ->first();
    }

    // ---------------------------------------------------------------
    // Queue processing
    // ---------------------------------------------------------------

    public function processJob(array $job): bool
    {
        $serviceId = (int) $job['service_id'];
        $service = $this->db->table('services')->where('id', $serviceId)->first();
        if (!$service) {
            return false;
        }
        return match ($job['action']) {
            'create', 'retry' => $this->provision($service),
            'suspend' => $this->suspend($serviceId, 'Queued suspend'),
            'unsuspend' => $this->unsuspend($serviceId),
            'terminate' => $this->terminate($serviceId),
            'install_ssl' => $this->installSsl($service),
            default => false,
        };
    }

    // ---------------------------------------------------------------
    // Full provisioning with rollback
    // ---------------------------------------------------------------

    public function provision(array $service): bool
    {
        $serviceId = (int) $service['id'];
        $this->log($serviceId, 'start', 'info', 'Provisioning started — ' . $service['domain']);

        $server = $this->assignServer($service['server_id'] ? (int) $service['server_id'] : null);
        if (!$server) {
            $this->log($serviceId, 'server', 'error', 'No online server available.');
            $this->fail($service, 'No available server');
            return false;
        }

        $aa = AaPanelService::forServer($server);
        $domain = $service['domain'];
        $sitePath = '/www/wwwroot/' . $domain;
        $php = $service['php_version'] ?? ($server['default_php'] ?? '82');

        // Credentials.
        $ftpUser = $service['username'] ?: akc_username($domain);
        $ftpPass = random_password(18);
        $dbUser = akc_username($domain, 'akc');
        $dbName = $dbUser . '_db';
        $dbPass = random_password(18);
        $sitePassword = random_password(14);

        $created = ['site' => false, 'siteId' => null];

        try {
            // 1) Create site + FTP + DB (bundled in AddSite).
            $res = $aa->createSite([
                'domain' => $domain,
                'aliases' => ['www.' . $domain],
                'path' => $sitePath,
                'php' => $php,
                'ps' => 'AKCloud #' . $serviceId,
                'service_id' => $serviceId,
                'ftp' => true, 'ftp_username' => $ftpUser, 'ftp_password' => $ftpPass,
                'sql' => true, 'datauser' => $dbUser, 'datapassword' => $dbPass,
            ]);
            if (!$res['status']) {
                throw new \RuntimeException('AddSite failed: ' . ($res['error'] ?? 'unknown'));
            }
            $created['site'] = true;
            $this->log($serviceId, 'create_site', 'success', 'Site, FTP and database created.');

            // Resolve the new aaPanel site id.
            $siteId = $this->resolveSiteId($aa, $domain);
            $created['siteId'] = $siteId;

            // 2) Save service details (encrypted secrets).
            $crypt = App::instance()->make('crypt');
            $this->db->table('service_details')->insert([
                'tenant_id' => $service['tenant_id'],
                'service_id' => $serviceId,
                'db_name' => $dbName, 'db_user' => $dbUser,
                'db_pass_enc' => $crypt->encrypt($dbPass), 'db_host' => '127.0.0.1',
                'ftp_user' => $ftpUser, 'ftp_pass_enc' => $crypt->encrypt($ftpPass),
                'ftp_host' => $server['ip_address'] ?: $server['hostname'], 'ftp_port' => 21,
                'ssl_status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('services')->where('id', $serviceId)->update([
                'server_id' => $server['id'],
                'aapanel_site_id' => $siteId,
                'site_path' => $sitePath,
                'username' => $ftpUser,
                'password_enc' => $crypt->encrypt($sitePassword),
                'reg_date' => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 3) SSL (best-effort — failure doesn't abort provisioning).
            $ssl = $aa->applySSL([$domain, 'www.' . $domain], (int) $siteId, $sitePath);
            if ($ssl['status']) {
                $aa->forceHttps($domain);
                $this->db->table('service_details')->where('service_id', $serviceId)
                    ->update(['ssl_status' => 'active', 'ssl_expires_at' => date('Y-m-d', time() + 89 * 86400)]);
                $this->log($serviceId, 'ssl', 'success', 'Let\'s Encrypt SSL installed.');
            } else {
                $this->log($serviceId, 'ssl', 'warning', 'SSL not issued yet — will retry once DNS has propagated.');
            }

            // 4) Isolation (per-site pool + user).
            $service['site_path'] = $sitePath;
            [$isoOk, $isoMsg] = (new IsolationService())->apply(array_merge($service, ['id' => $serviceId]), $php);
            $this->log($serviceId, 'isolation', $isoOk ? 'success' : 'warning', $isoMsg);

            // 5) Activate.
            $this->db->table('services')->where('id', $serviceId)->update([
                'status' => 'active', 'suspend_reason' => null, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('servers')->where('id', (int) $server['id'])->update([
                'active_accounts' => (int) $server['active_accounts'] + 1,
            ]);
            $this->log($serviceId, 'done', 'success', 'Service active ✅');

            // 6) Notify client (WhatsApp + Email).
            $this->notifyReady($serviceId, $domain, $ftpUser, $sitePassword, $server, $dbName, $dbUser, $dbPass);
            return true;

        } catch (\Throwable $e) {
            $this->log($serviceId, 'error', 'error', $e->getMessage());
            // ROLLBACK — remove partial artifacts.
            $this->rollback($aa, $created, $domain, $serviceId);
            $this->fail($service, $e->getMessage());
            return false;
        }
    }

    protected function resolveSiteId(AaPanelService $aa, string $domain): ?int
    {
        $sites = $aa->listSites(1, 50, $domain);
        $list = $sites['data'] ?? $sites;
        if (is_array($list)) {
            foreach ($list as $site) {
                if (($site['name'] ?? '') === $domain) {
                    return (int) $site['id'];
                }
            }
        }
        return null;
    }

    protected function rollback(AaPanelService $aa, array $created, string $domain, int $serviceId): void
    {
        if ($created['site'] && $created['siteId']) {
            $aa->deleteSite((int) $created['siteId'], $domain);
            $this->log($serviceId, 'rollback', 'info', 'Created site/db/ftp removed (rollback).');
        }
        $this->db->table('service_details')->where('service_id', $serviceId)->delete();
    }

    protected function fail(array $service, string $reason): void
    {
        $this->db->table('services')->where('id', (int) $service['id'])->update([
            'status' => 'pending', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // Alert admin via WhatsApp.
        $adminPhone = settings('general.admin_phone', '');
        if ($adminPhone) {
            (new WhatsAppService())->sendTemplate('provisioning_failed', (string) $adminPhone, [
                'domain' => $service['domain'],
            ]);
        }
    }

    protected function notifyReady(int $serviceId, string $domain, string $ftpUser, string $sitePass, array $server, string $dbName, string $dbUser, string $dbPass): void
    {
        $service = $this->db->table('services')->where('id', $serviceId)->first();
        $client = $this->db->table('clients')->where('id', (int) $service['client_id'])->first();
        if (!$client) {
            return;
        }
        $vars = [
            'client_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
            'domain' => $domain,
            'username' => $ftpUser,
            'password' => $sitePass,
            'ftp_host' => $server['ip_address'] ?: $server['hostname'],
            'ftp_user' => $ftpUser,
            'ftp_pass' => $sitePass,
            'db_name' => $dbName, 'db_user' => $dbUser, 'db_pass' => $dbPass,
            'panel_url' => rtrim((string) settings('general.app_url', config('app.url', '')), '/') . '/client',
            'company_name' => settings('general.company_name', 'AK Cloud'),
        ];
        if (!empty($client['phone'])) {
            (new WhatsAppService())->sendTemplate('hosting_ready', $client['phone'], $vars);
        }
        // Queue email too.
        $this->queueEmail($client['email'] ?? '', 'hosting_ready', $vars);
    }

    protected function queueEmail(string $to, string $slug, array $vars): void
    {
        if ($to === '') {
            return;
        }
        $tpl = $this->db->table('email_templates')->where('slug', $slug)->where('is_active', 1)->first();
        if (!$tpl) {
            return;
        }
        $subject = $tpl['subject'];
        $body = $tpl['body'];
        foreach ($vars as $k => $v) {
            $subject = str_replace('{' . $k . '}', (string) $v, $subject);
            $body = str_replace('{' . $k . '}', (string) $v, $body);
        }
        $this->db->table('email_queue')->insert([
            'tenant_id' => $vars['tenant_id'] ?? null,
            'to_email' => $to, 'to_name' => $vars['client_name'] ?? '',
            'subject' => $subject, 'body' => $body,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ---------------------------------------------------------------
    // Lifecycle: suspend / unsuspend / terminate / ssl
    // ---------------------------------------------------------------

    public function suspend(int $serviceId, string $reason = ''): bool
    {
        $service = $this->db->table('services')->where('id', $serviceId)->first();
        if (!$service || !$service['server_id']) {
            return false;
        }
        $server = $this->db->table('servers')->where('id', (int) $service['server_id'])->first();
        if ($server && $service['aapanel_site_id']) {
            AaPanelService::forServer($server)->stopSite((int) $service['aapanel_site_id'], $service['domain']);
        }
        $this->db->table('services')->where('id', $serviceId)->update([
            'status' => 'suspended', 'suspend_reason' => $reason, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->log($serviceId, 'suspend', 'info', 'Suspended: ' . $reason);
        $this->notifyStatus($service, 'suspended');
        return true;
    }

    public function unsuspend(int $serviceId): bool
    {
        $service = $this->db->table('services')->where('id', $serviceId)->first();
        if (!$service || !$service['server_id']) {
            return false;
        }
        $server = $this->db->table('servers')->where('id', (int) $service['server_id'])->first();
        if ($server && $service['aapanel_site_id']) {
            AaPanelService::forServer($server)->startSite((int) $service['aapanel_site_id'], $service['domain']);
        }
        $this->db->table('services')->where('id', $serviceId)->update([
            'status' => 'active', 'suspend_reason' => null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // Resolve open quota warnings.
        $this->db->table('quota_warnings')->where('service_id', $serviceId)->where('resolved', 0)->update(['resolved' => 1]);
        $this->log($serviceId, 'unsuspend', 'info', 'Unsuspended');
        $this->notifyStatus($service, 'unsuspended');
        return true;
    }

    public function terminate(int $serviceId): bool
    {
        $service = $this->db->table('services')->where('id', $serviceId)->first();
        if (!$service) {
            return false;
        }
        if ($service['server_id'] && $service['aapanel_site_id']) {
            $server = $this->db->table('servers')->where('id', (int) $service['server_id'])->first();
            if ($server) {
                AaPanelService::forServer($server)->deleteSite((int) $service['aapanel_site_id'], $service['domain']);
                (new IsolationService())->remove(array_merge($service, ['site_path' => $service['site_path']]), $service['php_version'] ?? '82');
                $this->db->table('servers')->where('id', (int) $server['id'])
                    ->update(['active_accounts' => max(0, (int) $server['active_accounts'] - 1)]);
            }
        }
        $this->db->table('services')->where('id', $serviceId)->update([
            'status' => 'terminated', 'termination_date' => date('Y-m-d'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->log($serviceId, 'terminate', 'info', 'Terminated');
        return true;
    }

    public function installSsl(array $service): bool
    {
        if (!$service['server_id'] || !$service['aapanel_site_id']) {
            return false;
        }
        $server = $this->db->table('servers')->where('id', (int) $service['server_id'])->first();
        $aa = AaPanelService::forServer($server);
        $res = $aa->applySSL([$service['domain'], 'www.' . $service['domain']], (int) $service['aapanel_site_id'], $service['site_path']);
        if ($res['status']) {
            $aa->forceHttps($service['domain']);
            $this->db->table('service_details')->where('service_id', (int) $service['id'])
                ->update(['ssl_status' => 'active', 'ssl_expires_at' => date('Y-m-d', time() + 89 * 86400)]);
            $this->log((int) $service['id'], 'ssl', 'success', 'SSL installed.');
            return true;
        }
        $this->log((int) $service['id'], 'ssl', 'error', 'SSL failed: ' . ($res['error'] ?? ''));
        return false;
    }

    protected function notifyStatus(array $service, string $event): void
    {
        $client = $this->db->table('clients')->where('id', (int) $service['client_id'])->first();
        if (!$client || empty($client['phone'])) {
            return;
        }
        (new WhatsAppService())->sendTemplate($event, $client['phone'], [
            'client_name' => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
            'domain' => $service['domain'],
            'panel_url' => rtrim((string) settings('general.app_url', config('app.url', '')), '/') . '/client',
            'company_name' => settings('general.company_name', 'AK Cloud'),
        ]);
    }

    // ---------------------------------------------------------------
    // Queue helpers + logging
    // ---------------------------------------------------------------

    public function enqueue(int $serviceId, string $action = 'create', array $payload = []): int
    {
        return $this->db->table('provisioning_queue')->insert([
            'tenant_id' => auth()->tenantId() ?? $this->tenantOf($serviceId),
            'service_id' => $serviceId,
            'action' => $action,
            'payload' => $payload ? json_encode($payload) : null,
            'status' => 'pending',
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tenantOf(int $serviceId): ?int
    {
        $s = $this->db->table('services')->where('id', $serviceId)->first();
        return $s ? (int) $s['tenant_id'] : null;
    }

    public function log(int $serviceId, string $step, string $status, string $message): void
    {
        $this->db->table('provisioning_logs')->insert([
            'tenant_id' => $this->tenantOf($serviceId),
            'service_id' => $serviceId,
            'step' => $step, 'status' => $status, 'message' => $message,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
