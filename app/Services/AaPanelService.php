<?php
// FILE: /app/Services/AaPanelService.php
// -------------------------------------------------------------------
// ⭐ MODULE 5 — aaPanel (BT Panel) API integration.
// Signature auth (request_time + request_token), ફરજિયાત cookie jar,
// retries with backoff, અને દરેક call `aapanel_logs` માં log.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class AaPanelService
{
    protected int $serverId;
    protected string $panelUrl;
    protected string $apiKey;      // api_sk (decrypted)
    protected bool $verifySsl;
    protected int $timeout = 30;
    protected int $connectTimeout = 10;
    protected int $retries = 3;
    protected ?int $tenantId = null;

    public function __construct(int $serverId, string $panelUrl, string $apiKey, bool $verifySsl = false, ?int $tenantId = null)
    {
        $this->serverId = $serverId;
        $this->panelUrl = rtrim($panelUrl, '/');
        $this->apiKey = $apiKey;
        $this->verifySsl = $verifySsl;
        $this->tenantId = $tenantId;
    }

    /**
     * Build a service from a `servers` table row (decrypts the API key).
     */
    public static function forServer(array $server): self
    {
        $key = $server['api_key'] ?? '';
        // Stored encrypted — decrypt; if it isn't encrypted (plain), keep as-is.
        $decoded = App::instance()->make('crypt')->decrypt($key);
        if ($decoded !== null) {
            $key = $decoded;
        }
        return new self(
            (int) $server['id'],
            (string) $server['panel_url'],
            (string) $key,
            (bool) ($server['verify_ssl'] ?? false),
            isset($server['tenant_id']) ? (int) $server['tenant_id'] : null
        );
    }

    // ---------------------------------------------------------------
    // 5.1 Signature + 5.2 core request
    // ---------------------------------------------------------------

    protected function signature(): array
    {
        $requestTime = time();
        return [
            'request_time' => $requestTime,
            'request_token' => md5($requestTime . md5($this->apiKey)),
        ];
    }

    protected function cookieFile(): string
    {
        $dir = App::instance()->storagePath('temp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/aapanel_cookie_' . $this->serverId . '.txt';
    }

    /**
     * Core request — POST {panel_url}{endpoint} with signature params.
     * Retries on transport failure with exponential backoff.
     *
     * @return array{status:bool,data:mixed,http_code:int,raw:string,error:?string}
     */
    public function request(string $endpoint, array $params = []): array
    {
        $url = $this->panelUrl . $endpoint;
        $payload = array_merge($params, $this->signature());
        $cookie = $this->cookieFile();

        $lastError = null;
        $start = microtime(true);
        $httpCode = 0;
        $raw = '';

        for ($attempt = 1; $attempt <= $this->retries; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
                CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_COOKIEJAR => $cookie,     // ફરજિયાત — session save
                CURLOPT_COOKIEFILE => $cookie,    // ફરજિયાત — session send
                CURLOPT_USERAGENT => 'AKCloud/1.0 (+https://akdwk.in)',
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $lastError = $errno ? curl_error($ch) : null;
            curl_close($ch);

            if ($raw !== false && $errno === 0) {
                break;
            }
            $raw = '';
            if ($attempt < $this->retries) {
                usleep((int) (pow(2, $attempt) * 250000)); // 0.5s, 1s, 2s
            }
        }

        $duration = (int) round((microtime(true) - $start) * 1000);
        $data = $raw !== '' ? json_decode($raw, true) : null;

        // aaPanel success: status field true, or presence of expected data.
        $status = is_array($data)
            ? ($data['status'] ?? true) !== false
            : ($raw !== '' && $lastError === null);

        $result = [
            'status' => (bool) $status,
            'data' => $data ?? $raw,
            'http_code' => $httpCode,
            'raw' => (string) $raw,
            'error' => $lastError ?? (is_array($data) && isset($data['msg']) && $status === false ? (string) $data['msg'] : null),
        ];

        $this->logCall($endpoint, $params, $result, $duration);
        return $result;
    }

    protected function logCall(string $endpoint, array $params, array $result, int $duration): void
    {
        try {
            // Mask sensitive params before logging.
            $masked = $params;
            foreach (['ftp_password', 'datapassword', 'password', 'db_pass'] as $k) {
                if (isset($masked[$k])) {
                    $masked[$k] = '***';
                }
            }
            App::instance()->make('db')->table('aapanel_logs')->insert([
                'server_id' => $this->serverId,
                'tenant_id' => $this->tenantId,
                'endpoint' => $endpoint,
                'method' => $this->extractAction($endpoint),
                'params' => json_encode($masked, JSON_UNESCAPED_UNICODE),
                'response' => mb_substr((string) $result['raw'], 0, 8000),
                'http_code' => $result['http_code'],
                'status' => $result['status'] ? 'success' : ($result['error'] ? 'error' : 'failed'),
                'duration_ms' => $duration,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Logging failure must never break provisioning.
        }
    }

    protected function extractAction(string $endpoint): string
    {
        parse_str((string) parse_url($endpoint, PHP_URL_QUERY), $q);
        return $q['action'] ?? $endpoint;
    }

    // ---------------------------------------------------------------
    // 5.3 SYSTEM
    // ---------------------------------------------------------------

    public function testConnection(): array
    {
        $res = $this->request('/system?action=GetSystemTotal');
        return [
            'ok' => $res['status'] && is_array($res['data']) && isset($res['data']['memTotal']),
            'data' => $res['data'],
            'error' => $res['error'],
        ];
    }

    public function getSystemInfo(): array
    {
        return $this->request('/system?action=GetSystemTotal')['data'] ?? [];
    }

    public function getDiskInfo(): array
    {
        return $this->request('/system?action=GetDiskInfo')['data'] ?? [];
    }

    public function getNetworkStats(): array
    {
        return $this->request('/system?action=GetNetWork')['data'] ?? [];
    }

    public function getInstalledPhpVersions(): array
    {
        return $this->request('/site?action=GetPHPVersion')['data'] ?? [];
    }

    public function serviceControl(string $name, string $type): array
    {
        // $type: start|stop|restart|reload
        return $this->request('/system?action=ServiceAdmin', ['name' => $name, 'type' => $type]);
    }

    // ---------------------------------------------------------------
    // 5.3 WEBSITE
    // ---------------------------------------------------------------

    public function listSites(int $page = 1, int $limit = 20, string $search = ''): array
    {
        return $this->request('/data?action=getData&table=sites', [
            'p' => $page, 'limit' => $limit, 'search' => $search, 'type' => -1, 'order' => 'id desc',
        ])['data'] ?? [];
    }

    /**
     * createSite — full site + FTP + database in one call (Module 5 spec).
     *
     * @param array $opts domain, aliases[], path?, php='82', port='80',
     *                    ps, ftp(bool), ftp_username, ftp_password,
     *                    sql(bool), datauser, datapassword, service_id
     */
    public function createSite(array $opts): array
    {
        $domain = $opts['domain'];
        $path = $opts['path'] ?? ('/www/wwwroot/' . $domain);
        $aliases = $opts['aliases'] ?? ['www.' . $domain];

        $params = [
            'webname' => json_encode([
                'domain' => $domain,
                'domainlist' => array_values($aliases),
                'count' => 0,
            ]),
            'path' => $path,
            'type_id' => 0,
            'type' => 'PHP',
            'version' => $opts['php'] ?? '82',
            'port' => (string) ($opts['port'] ?? '80'),
            'ps' => $opts['ps'] ?? ('AKCloud #' . ($opts['service_id'] ?? '')),
        ];

        if (!empty($opts['ftp'])) {
            $params['ftp'] = 'true';
            $params['ftp_username'] = $opts['ftp_username'];
            $params['ftp_password'] = $opts['ftp_password'];
        } else {
            $params['ftp'] = 'false';
        }

        if (!empty($opts['sql'])) {
            $params['sql'] = 'MySQL';
            $params['codeing'] = 'utf8mb4';
            $params['datauser'] = $opts['datauser'];
            $params['datapassword'] = $opts['datapassword'];
        } else {
            $params['sql'] = 'false';
        }

        return $this->request('/site?action=AddSite', $params);
    }

    public function deleteSite(int $siteId, string $siteName): array
    {
        return $this->request('/site?action=DeleteSite', [
            'id' => $siteId, 'webname' => $siteName,
            'ftp' => 1, 'database' => 1, 'path' => 1,
        ]);
    }

    public function stopSite(int $siteId, string $siteName): array
    {
        return $this->request('/site?action=SiteStop', ['id' => $siteId, 'name' => $siteName]);
    }

    public function startSite(int $siteId, string $siteName): array
    {
        return $this->request('/site?action=SiteStart', ['id' => $siteId, 'name' => $siteName]);
    }

    public function setExpiry(int $siteId, string $date): array
    {
        // $date 'YYYY-MM-DD' or '0000-00-00' for永久.
        return $this->request('/site?action=SetEdate', ['id' => $siteId, 'edate' => $date]);
    }

    public function addDomain(int $siteId, string $siteName, string $domain, string $port = '80'): array
    {
        return $this->request('/site?action=AddDomain', [
            'id' => $siteId, 'webname' => $siteName, 'domain' => $domain . ':' . $port,
        ]);
    }

    public function deleteDomain(int $siteId, string $siteName, string $domain, string $port = '80'): array
    {
        return $this->request('/site?action=DelDomain', [
            'id' => $siteId, 'webname' => $siteName, 'domain' => $domain, 'port' => $port,
        ]);
    }

    public function getDomains(int $siteId): array
    {
        return $this->request('/data?action=getData&table=domain', ['search' => $siteId, 'list' => 'true'])['data'] ?? [];
    }

    public function setPhpVersion(string $siteName, string $version): array
    {
        return $this->request('/site?action=SetPHPVersion', ['siteName' => $siteName, 'version' => $version]);
    }

    public function setSiteLimit(int $siteId, int $perserver, int $perip, int $rate): array
    {
        return $this->request('/site?action=SetLimitNet', [
            'id' => $siteId, 'perserver' => $perserver, 'perip' => $perip, 'limit_rate' => $rate,
        ]);
    }

    public function getSiteLogs(string $siteName): array
    {
        return $this->request('/site?action=GetSiteLogs', ['siteName' => $siteName]);
    }

    public function backupSite(int $siteId): array
    {
        return $this->request('/site?action=ToBackup', ['id' => $siteId]);
    }

    // ---------------------------------------------------------------
    // 5.3 DATABASE
    // ---------------------------------------------------------------

    public function listDatabases(int $page = 1, int $limit = 20, string $search = ''): array
    {
        return $this->request('/data?action=getData&table=databases', [
            'p' => $page, 'limit' => $limit, 'search' => $search,
        ])['data'] ?? [];
    }

    public function createDatabase(string $name, string $user, string $password): array
    {
        return $this->request('/database?action=AddDatabase', [
            'name' => $name, 'db_user' => $user, 'password' => $password,
            'dtype' => 'MySQL', 'codeing' => 'utf8mb4',
            'dataAccess' => '127.0.0.1', 'address' => '127.0.0.1',
            'ps' => 'AKCloud ' . $name,
        ]);
    }

    public function deleteDatabase(int $id, string $name): array
    {
        return $this->request('/database?action=DeleteDatabase', ['id' => $id, 'name' => $name]);
    }

    public function changeDbPassword(int $id, string $name, string $password): array
    {
        return $this->request('/database?action=ResDatabasePassword', ['id' => $id, 'name' => $name, 'password' => $password]);
    }

    public function backupDatabase(int $id): array
    {
        return $this->request('/database?action=ToBackup', ['id' => $id]);
    }

    // ---------------------------------------------------------------
    // 5.3 FTP
    // ---------------------------------------------------------------

    public function listFtp(int $page = 1, int $limit = 20, string $search = ''): array
    {
        return $this->request('/data?action=getData&table=ftps', [
            'p' => $page, 'limit' => $limit, 'search' => $search,
        ])['data'] ?? [];
    }

    public function createFtp(string $user, string $password, string $path): array
    {
        return $this->request('/ftp?action=AddUser', [
            'ftp_username' => $user, 'ftp_password' => $password, 'path' => $path, 'ps' => 'AKCloud',
        ]);
    }

    public function deleteFtp(int $id, string $user): array
    {
        return $this->request('/ftp?action=DeleteUser', ['id' => $id, 'username' => $user]);
    }

    public function changeFtpPassword(int $id, string $user, string $password): array
    {
        return $this->request('/ftp?action=SetUserPassword', ['id' => $id, 'ftp_username' => $user, 'new_password' => $password]);
    }

    public function setFtpStatus(int $id, string $user, int $status): array
    {
        return $this->request('/ftp?action=SetStatus', ['id' => $id, 'username' => $user, 'status' => $status]);
    }

    // ---------------------------------------------------------------
    // 5.3 SSL (Let's Encrypt free auto SSL)
    // ---------------------------------------------------------------

    public function applySSL(array $domains, int $siteId, string $sitePath): array
    {
        return $this->request('/acme?action=apply_cert_api', [
            'domains' => json_encode(array_values($domains)),
            'auth_type' => 'http',
            'auth_to' => $sitePath,
            'auto_wr' => 1,
            'id' => $siteId,
        ]);
    }

    public function getSSL(string $siteName): array
    {
        return $this->request('/site?action=GetSSL', ['siteName' => $siteName]);
    }

    public function setSSL(string $type, string $siteName, string $key, string $csr): array
    {
        return $this->request('/site?action=SetSSL', ['type' => $type, 'siteName' => $siteName, 'key' => $key, 'csr' => $csr]);
    }

    public function forceHttps(string $siteName): array
    {
        return $this->request('/site?action=HttpToHttps', ['siteName' => $siteName]);
    }

    public function closeHttps(string $siteName): array
    {
        return $this->request('/site?action=CloseToHttps', ['siteName' => $siteName]);
    }

    // ---------------------------------------------------------------
    // 5.3 FILES (client file manager — path restricted by ProvisioningService)
    // ---------------------------------------------------------------

    public function getDir(string $path): array
    {
        return $this->request('/files?action=GetDir', ['path' => $path]);
    }

    public function getFileBody(string $path): array
    {
        return $this->request('/files?action=GetFileBody', ['path' => $path]);
    }

    public function saveFileBody(string $path, string $data, string $encoding = 'utf-8'): array
    {
        return $this->request('/files?action=SaveFileBody', ['path' => $path, 'data' => $data, 'encoding' => $encoding]);
    }

    public function createDir(string $path): array
    {
        return $this->request('/files?action=CreateDir', ['path' => $path]);
    }

    public function createFile(string $path): array
    {
        return $this->request('/files?action=CreateFile', ['path' => $path]);
    }

    public function deleteFile(string $path): array
    {
        return $this->request('/files?action=DeleteFile', ['path' => $path]);
    }

    public function deleteDir(string $path): array
    {
        return $this->request('/files?action=DeleteDir', ['path' => $path]);
    }

    public function zip(string $path, string $sfile): array
    {
        return $this->request('/files?action=Zip', ['path' => $path, 'sfile' => $sfile, 'type' => 'zip']);
    }

    public function unzip(string $path, string $dfile): array
    {
        return $this->request('/files?action=UnZip', ['path' => $path, 'dfile' => $dfile, 'type' => 'zip']);
    }

    public function setFileAccess(string $filename, string $user, string $access): array
    {
        return $this->request('/files?action=SetFileAccess', ['filename' => $filename, 'user' => $user, 'access' => $access]);
    }

    // ---------------------------------------------------------------
    // 5.3 CRON
    // ---------------------------------------------------------------

    public function getCrontab(): array
    {
        return $this->request('/crontab?action=GetCrontab')['data'] ?? [];
    }

    public function addCrontab(array $params): array
    {
        return $this->request('/crontab?action=AddCrontab', $params);
    }

    public function delCrontab(int $id): array
    {
        return $this->request('/crontab?action=DelCrontab', ['id' => $id]);
    }
}
