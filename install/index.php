<?php
// FILE: /install/index.php
// -------------------------------------------------------------------
// MODULE 1 — Installer. 10-step wizard. કોઈ file manually edit ન કરવી
// પડે — છેલ્લે .env + config/config.php + installed.lock generate થાય.
// -------------------------------------------------------------------

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$base = dirname(__DIR__);
$lock = $base . '/install/installed.lock';

require $base . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register($base);

// Already installed guard.
if (is_file($lock) && !isset($_GET['force'])) {
    http_response_code(403);
    echo installer_page('Already installed', '<div class="alert alert-info">AK Cloud પહેલેથી install થયેલું છે. ફરી install કરવા <code>install/installed.lock</code> delete કરો.</div><a class="btn btn-primary" href="' . rtrim(base_url(), '/') . '/login">લોગિન પર જાઓ</a>');
    exit;
}

$action = $_GET['action'] ?? null;
$step = (int) ($_GET['step'] ?? 1);

// ---- AJAX / POST handlers ------------------------------------------
if ($action === 'test_db') {
    header('Content-Type: application/json');
    echo json_encode(test_db($_POST));
    exit;
}
if ($action === 'test_aapanel') {
    header('Content-Type: application/json');
    echo json_encode(test_aapanel($_POST));
    exit;
}
if ($action === 'test_whatsapp') {
    header('Content-Type: application/json');
    echo json_encode(test_whatsapp($_POST));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    handle_save($step);
    exit;
}

// ---- Render current step -------------------------------------------
echo render_step($step);
exit;

// ====================================================================
// FUNCTIONS
// ====================================================================

function base_url(): string
{
    $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $scheme . '://' . $host . $dir;
}

function install_url(int $step): string
{
    return base_url() . '/install/index.php?step=' . $step;
}

function test_db(array $p): array
{
    try {
        $dsn = "mysql:host={$p['host']};port={$p['port']};dbname={$p['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $p['user'], $p['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query('SELECT 1');
        return ['ok' => true, 'message' => '✅ ડેટાબેઝ કનેક્શન સફળ'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => '❌ ' . $e->getMessage()];
    }
}

function test_aapanel(array $p): array
{
    $url = rtrim($p['panel_url'], '/') . '/system?action=GetSystemTotal';
    $t = time();
    $token = md5($t . md5($p['api_key']));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['request_time' => $t, 'request_token' => $token]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/akc_install_cookie.txt',
        CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/akc_install_cookie.txt',
        CURLOPT_USERAGENT => 'AKCloud-Installer',
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string) $resp, true);
    if (is_array($data) && isset($data['memTotal'])) {
        return [
            'ok' => true,
            'message' => sprintf('✅ કનેક્ટ થયું — RAM: %s MB, CPU cores: %s',
                $data['memTotal'] ?? '?', is_array($data['cpuNum'] ?? null) ? count($data['cpuNum']) : ($data['cpuNum'] ?? '?')),
            'data' => $data,
        ];
    }
    return ['ok' => false, 'message' => '❌ કનેક્ટ ન થયું: ' . ($err ?: substr((string) $resp, 0, 200))];
}

function test_whatsapp(array $p): array
{
    $payload = [
        'api_key' => $p['api_key'],
        'number' => preg_replace('/\D/', '', $p['test_number']),
        'message' => 'AK Cloud installer થી ટેસ્ટ મેસેજ ✅',
        'session_id' => $p['session_id'],
    ];
    $ch = curl_init($p['api_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300
        ? ['ok' => true, 'message' => '✅ મેસેજ મોકલ્યો — WhatsApp તપાસો', 'raw' => substr((string) $resp, 0, 200)]
        : ['ok' => false, 'message' => '❌ નિષ્ફળ (HTTP ' . $code . ')'];
}

function handle_save(int $step): void
{
    switch ($step) {
        case 3: // Database
            $_SESSION['install']['db'] = [
                'host' => $_POST['host'], 'port' => $_POST['port'], 'name' => $_POST['name'],
                'user' => $_POST['user'], 'pass' => $_POST['pass'],
            ];
            redirect(4);
            break;
        case 4: // Schema import
            $result = import_schema($_SESSION['install']['db'] ?? []);
            if (!$result['ok']) {
                echo render_step(4, $result['message']);
                return;
            }
            redirect(5);
            break;
        case 5: // Admin
            $_SESSION['install']['admin'] = [
                'name' => $_POST['name'], 'email' => $_POST['email'],
                'mobile' => $_POST['mobile'], 'password' => $_POST['password'],
            ];
            redirect(6);
            break;
        case 6: // Site settings
            $_SESSION['install']['site'] = [
                'company' => $_POST['company'], 'url' => $_POST['url'],
                'timezone' => $_POST['timezone'] ?? 'Asia/Kolkata',
                'currency' => 'INR', 'gst' => $_POST['gst'] ?? '18',
                'gstin' => $_POST['gstin'] ?? '', 'state_code' => $_POST['state_code'] ?? '24',
            ];
            redirect(7);
            break;
        case 7: // aaPanel
            $_SESSION['install']['aapanel'] = [
                'panel_url' => $_POST['panel_url'], 'api_key' => $_POST['api_key'],
            ];
            redirect(8);
            break;
        case 8: // WhatsApp
            $_SESSION['install']['whatsapp'] = [
                'api_url' => $_POST['api_url'], 'api_key' => $_POST['api_key'],
                'session_id' => $_POST['session_id'],
            ];
            redirect(9);
            break;
        case 9: // cron acknowledged
            redirect(10);
            break;
        case 10: // finish
            $result = finalize();
            echo render_step(10, $result['ok'] ? '' : $result['message'], $result['ok']);
            return;
    }
}

function redirect(int $step): void
{
    header('Location: ' . install_url($step));
    exit;
}

function import_schema(array $db): array
{
    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(dirname(__DIR__) . '/install/sql/schema.sql');
        if ($sql === false) {
            return ['ok' => false, 'message' => 'schema.sql મળ્યું નહીં.'];
        }
        foreach (split_sql($sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        return ['ok' => true, 'message' => 'Schema imported'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Schema import error: ' . $e->getMessage()];
    }
}

/**
 * Quote-aware SQL splitter (handles ' " ` and escaped quotes).
 */
function split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $len = strlen($sql);
    $inString = false;
    $stringChar = '';
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $buffer .= $ch;
        if ($inString) {
            if ($ch === '\\') {
                $buffer .= $sql[++$i] ?? '';
                continue;
            }
            if ($ch === $stringChar) {
                $inString = false;
            }
        } else {
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $inString = true;
                $stringChar = $ch;
            } elseif ($ch === ';') {
                $statements[] = substr($buffer, 0, -1);
                $buffer = '';
            } elseif ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
                // Skip line comment.
                $eol = strpos($sql, "\n", $i);
                $buffer = substr($buffer, 0, -1);
                $i = $eol === false ? $len : $eol;
            }
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }
    return $statements;
}

function finalize(): array
{
    $s = $_SESSION['install'] ?? [];
    $base = dirname(__DIR__);
    try {
        $db = $s['db'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $appKey = \App\Core\Crypt::generateKey();
        $crypt = new \App\Core\Crypt($appKey);

        // 1) Create the super-admin user.
        $admin = $s['admin'];
        $hash = \App\Core\Crypt::hashPassword($admin['password']);
        $stmt = $pdo->prepare('INSERT INTO users (tenant_id, role_id, name, email, mobile, password, type, status, language, created_at, updated_at)
            VALUES (1, 1, ?, ?, ?, ?, "super_admin", "active", "gu", NOW(), NOW())');
        $stmt->execute([$admin['name'], $admin['email'], $admin['mobile'], $hash]);

        // 2) Persist settings (encrypt secrets).
        $site = $s['site'];
        $aap = $s['aapanel'];
        $wa = $s['whatsapp'];
        upsert_setting($pdo, 'general', 'company_name', $site['company']);
        upsert_setting($pdo, 'general', 'app_url', $site['url']);
        upsert_setting($pdo, 'general', 'timezone', $site['timezone']);
        upsert_setting($pdo, 'general', 'admin_phone', $admin['mobile']);
        upsert_setting($pdo, 'tax', 'gst_percent', $site['gst']);
        upsert_setting($pdo, 'tax', 'company_gstin', $site['gstin']);
        upsert_setting($pdo, 'tax', 'company_state_code', $site['state_code']);
        upsert_setting($pdo, 'whatsapp', 'api_url', $wa['api_url']);
        upsert_setting($pdo, 'whatsapp', 'api_key', $crypt->encrypt($wa['api_key']), 1);
        upsert_setting($pdo, 'whatsapp', 'session_id', $crypt->encrypt($wa['session_id']), 1);

        // 3) Register the local aaPanel server.
        $stmt = $pdo->prepare('INSERT INTO servers (tenant_id, name, panel_url, api_key, ip_address, type, verify_ssl, is_default, status, created_at, updated_at)
            VALUES (1, "Local aaPanel", ?, ?, "127.0.0.1", "aapanel", 0, 1, "online", NOW(), NOW())');
        $stmt->execute([$aap['panel_url'], $crypt->encrypt($aap['api_key'])]);

        // 4) Write .env.
        $phpBin = detect_php_bin();
        $env = build_env($appKey, $db, $site, $aap, $wa, $phpBin);
        if (@file_put_contents($base . '/.env', $env) === false) {
            throw new RuntimeException('.env લખી શકાયું નહીં — folder permission તપાસો.');
        }

        // 5) Write config/config.php.
        $config = build_config($appKey, $db, $site);
        if (@file_put_contents($base . '/config/config.php', $config) === false) {
            throw new RuntimeException('config/config.php લખી શકાયું નહીં.');
        }

        // 6) Lock the installer.
        file_put_contents($base . '/install/installed.lock', date('c') . ' — installed');

        // 7) Record migrations baseline.
        $pdo->exec('INSERT IGNORE INTO migrations (migration, batch, applied_at) VALUES ("0000_00_00_000000_baseline_schema.sql", 1, NOW())');

        session_destroy();
        return ['ok' => true, 'message' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function upsert_setting(PDO $pdo, string $group, string $key, string $value, int $enc = 0): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (tenant_id, `group`, `key`, `value`, is_encrypted, created_at, updated_at)
        VALUES (NULL, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), is_encrypted = VALUES(is_encrypted), updated_at = NOW()');
    $stmt->execute([$group, $key, $value, $enc]);
}

function detect_php_bin(): string
{
    foreach (['82', '83', '81', '80'] as $v) {
        if (is_file("/www/server/php/{$v}/bin/php")) {
            return "/www/server/php/{$v}/bin/php";
        }
    }
    return PHP_BINARY ?: 'php';
}

function build_env(string $key, array $db, array $site, array $aap, array $wa, string $phpBin): string
{
    return "APP_NAME=\"{$site['company']}\"\nAPP_ENV=production\nAPP_DEBUG=false\n"
        . "APP_URL={$site['url']}\nAPP_TIMEZONE={$site['timezone']}\nAPP_LOCALE=gu\n"
        . "APP_KEY={$key}\n\n"
        . "DB_HOST={$db['host']}\nDB_PORT={$db['port']}\nDB_NAME={$db['name']}\n"
        . "DB_USER={$db['user']}\nDB_PASS=\"{$db['pass']}\"\nDB_CHARSET=utf8mb4\n\n"
        . "AAPANEL_URL={$aap['panel_url']}\nAAPANEL_VERIFY_SSL=false\n\n"
        . "WHATSAPP_API_URL={$wa['api_url']}\n\nPHP_BIN={$phpBin}\n\n"
        . "SESSION_SECURE=true\nSESSION_SAMESITE=Lax\n";
}

function build_config(string $key, array $db, array $site): string
{
    $pass = addslashes($db['pass']);
    $url = addslashes($site['url']);
    $company = addslashes($site['company']);
    return <<<PHP
<?php
// FILE: /config/config.php  (installer-generated — NEVER overwritten by updater)
return [
    'app' => [
        'name' => '{$company}', 'env' => 'production', 'debug' => false,
        'url' => '{$url}', 'timezone' => '{$site['timezone']}', 'locale' => 'gu',
        'currency_symbol' => '₹', 'repository' => 'akshaykananidwk/hosting.akdwk.in',
        'key' => '{$key}',
    ],
    'db' => [
        'host' => '{$db['host']}', 'port' => {$db['port']}, 'name' => '{$db['name']}',
        'user' => '{$db['user']}', 'pass' => '{$pass}', 'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    'aapanel' => ['url' => '', 'verify_ssl' => false, 'timeout' => 30, 'connect_timeout' => 10, 'retries' => 3],
    'paths' => ['php_bin' => '', 'storage' => __DIR__ . '/../storage', 'uploads' => __DIR__ . '/../public/uploads', 'backups' => __DIR__ . '/../storage/backups'],
    'session' => ['name' => 'akc_session', 'lifetime' => 7200, 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'],
    'security' => ['max_login_attempts' => 5, 'lockout_minutes' => 15, 'admin_ip_whitelist' => []],
    'mail' => ['host' => '', 'port' => 587, 'username' => '', 'password' => '', 'encryption' => 'tls', 'from_email' => '', 'from_name' => '{$company}'],
];

PHP;
}

// ====================================================================
// VIEWS
// ====================================================================

function render_step(int $step, string $error = '', bool $done = false): string
{
    $steps = [
        1 => 'Welcome', 2 => 'Requirements', 3 => 'Database', 4 => 'Schema',
        5 => 'Admin', 6 => 'Site', 7 => 'aaPanel', 8 => 'WhatsApp', 9 => 'Cron', 10 => 'Finish',
    ];
    $body = '<div class="stepper">';
    foreach ($steps as $n => $name) {
        $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
        $body .= "<div class='step {$cls}'><span>{$n}</span>{$name}</div>";
    }
    $body .= '</div>';

    if ($error !== '') {
        $body .= '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
    }

    $body .= match ($step) {
        1 => step_welcome(),
        2 => step_requirements(),
        3 => step_database(),
        4 => step_schema(),
        5 => step_admin(),
        6 => step_site(),
        7 => step_aapanel(),
        8 => step_whatsapp(),
        9 => step_cron(),
        10 => $done ? step_done() : step_finish(),
        default => step_welcome(),
    };

    return installer_page($steps[$step] ?? 'Install', $body);
}

function step_welcome(): string
{
    return '<h2>☁️ AK Cloud માં આપનું સ્વાગત છે</h2>
    <p class="muted">આ wizard 10 પગલાંમાં setup પૂરું કરશે — કોઈ file manually edit કરવાની જરૂર નથી.</p>
    <ul><li>Requirement check</li><li>Database + schema</li><li>Admin એકાઉન્ટ</li><li>aaPanel + WhatsApp કનેક્શન</li><li>Cron + finish</li></ul>
    <a class="btn btn-primary" href="?step=2">શરૂ કરો →</a>';
}

function step_requirements(): string
{
    $checks = [
        'PHP ≥ 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'mbstring' => extension_loaded('mbstring'),
        'openssl' => extension_loaded('openssl'),
        'zip' => extension_loaded('zip'),
        'gd' => extension_loaded('gd'),
        'json' => extension_loaded('json'),
        'fileinfo' => extension_loaded('fileinfo'),
        'exif' => extension_loaded('exif'),
        'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
        'memory ≥ 256M' => memory_ok(),
        'config/ writable' => is_writable(dirname(__DIR__) . '/config'),
        'storage/ writable' => is_writable(dirname(__DIR__) . '/storage'),
        '.env writable (root)' => is_writable(dirname(__DIR__)),
    ];
    $rows = '';
    $allOk = true;
    foreach ($checks as $label => $ok) {
        $allOk = $allOk && $ok;
        $icon = $ok ? '✅' : '❌';
        $fix = $ok ? '' : '<span class="muted small">— install/enable કરો</span>';
        $rows .= "<tr><td>{$label}</td><td>{$icon} {$fix}</td></tr>";
    }
    $next = $allOk
        ? '<a class="btn btn-primary" href="?step=3">આગળ →</a>'
        : '<div class="alert alert-warning">બધી ❌ પહેલા ઠીક કરો, પછી refresh કરો.</div>';
    return "<h2>Requirement Check</h2><table class='table'>{$rows}</table>{$next}";
}

function memory_ok(): bool
{
    $limit = ini_get('memory_limit');
    if ($limit === '-1') {
        return true;
    }
    return (int) $limit >= 256 || str_contains(strtoupper($limit), 'G');
}

function step_database(): string
{
    $d = $_SESSION['install']['db'] ?? ['host' => '127.0.0.1', 'port' => '3306', 'name' => 'akcloud', 'user' => 'akcloud', 'pass' => ''];
    return '<h2>Database</h2>
    <form method="post" action="?step=3&action=save" id="dbform">
      <div class="grid cols-2">
        <div class="form-group"><label>Host</label><input name="host" value="' . h($d['host']) . '" required></div>
        <div class="form-group"><label>Port</label><input name="port" value="' . h($d['port']) . '" required></div>
      </div>
      <div class="form-group"><label>Database Name</label><input name="name" value="' . h($d['name']) . '" required></div>
      <div class="grid cols-2">
        <div class="form-group"><label>User</label><input name="user" value="' . h($d['user']) . '" required></div>
        <div class="form-group"><label>Password</label><input name="pass" type="password" value="' . h($d['pass']) . '"></div>
      </div>
      <div class="flex gap-2">
        <button type="button" class="btn btn-outline" onclick="testDb()">Test Connection</button>
        <button type="submit" class="btn btn-primary">આગળ →</button>
      </div>
      <div id="dbresult" class="mt-2"></div>
    </form>
    <script>
    function testDb(){var f=document.getElementById("dbform");var d=new FormData(f);
      fetch("?action=test_db",{method:"POST",body:d}).then(r=>r.json()).then(j=>{
        document.getElementById("dbresult").innerHTML="<div class=\'alert alert-"+(j.ok?"success":"danger")+"\'>"+j.message+"</div>";});}
    </script>';
}

function step_schema(): string
{
    $count = count_tables();
    return '<h2>Schema Import</h2>
    <p class="muted">Schema.sql (64 tables + seed data) database માં import થશે.</p>
    <p>' . ($count > 0 ? "<span class='badge badge-info'>{$count} tables પહેલેથી છે — ફરી import overwrite નહીં કરે (IF NOT EXISTS)</span>" : '') . '</p>
    <form method="post" action="?step=4&action=save">
      <button type="submit" class="btn btn-primary">Import કરો →</button>
    </form>';
}

function count_tables(): int
{
    try {
        $db = $_SESSION['install']['db'] ?? null;
        if (!$db) {
            return 0;
        }
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass']);
        return (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function step_admin(): string
{
    $a = $_SESSION['install']['admin'] ?? ['name' => '', 'email' => '', 'mobile' => ''];
    return '<h2>Super Admin એકાઉન્ટ</h2>
    <form method="post" action="?step=5&action=save">
      <div class="form-group"><label>નામ</label><input name="name" value="' . h($a['name']) . '" required></div>
      <div class="form-group"><label>ઈમેલ</label><input name="email" type="email" value="' . h($a['email']) . '" required></div>
      <div class="form-group"><label>મોબાઈલ (WhatsApp alerts)</label><input name="mobile" value="' . h($a['mobile']) . '" required></div>
      <div class="form-group"><label>પાસવર્ડ</label><input name="password" type="password" minlength="8" required></div>
      <button type="submit" class="btn btn-primary">આગળ →</button>
    </form>';
}

function step_site(): string
{
    $s = $_SESSION['install']['site'] ?? ['company' => 'AK Cloud', 'url' => base_url(), 'gst' => '18', 'gstin' => '', 'state_code' => '24'];
    return '<h2>Site Settings</h2>
    <form method="post" action="?step=6&action=save">
      <div class="form-group"><label>Company Name</label><input name="company" value="' . h($s['company']) . '" required></div>
      <div class="form-group"><label>Panel URL</label><input name="url" value="' . h($s['url']) . '" required></div>
      <input type="hidden" name="timezone" value="Asia/Kolkata">
      <div class="grid cols-3">
        <div class="form-group"><label>GST %</label><input name="gst" value="' . h($s['gst']) . '"></div>
        <div class="form-group"><label>Company GSTIN</label><input name="gstin" value="' . h($s['gstin']) . '"></div>
        <div class="form-group"><label>State Code</label><input name="state_code" value="' . h($s['state_code']) . '"></div>
      </div>
      <button type="submit" class="btn btn-primary">આગળ →</button>
    </form>';
}

function step_aapanel(): string
{
    $a = $_SESSION['install']['aapanel'] ?? ['panel_url' => 'http://127.0.0.1:35435', 'api_key' => ''];
    return '<h2>aaPanel કનેક્શન</h2>
    <p class="muted small">aaPanel → Settings → API Interface → ON → API Key copy → IP whitelist માં 127.0.0.1</p>
    <form method="post" action="?step=7&action=save" id="aaform">
      <div class="form-group"><label>Panel URL</label><input name="panel_url" value="' . h($a['panel_url']) . '" required></div>
      <div class="form-group"><label>API Key (api_sk)</label><input name="api_key" value="' . h($a['api_key']) . '" required></div>
      <div class="flex gap-2">
        <button type="button" class="btn btn-outline" onclick="testAa()">Test Connection</button>
        <button type="submit" class="btn btn-primary">આગળ →</button>
      </div>
      <div id="aaresult" class="mt-2"></div>
    </form>
    <script>function testAa(){var d=new FormData(document.getElementById("aaform"));
      fetch("?action=test_aapanel",{method:"POST",body:d}).then(r=>r.json()).then(j=>{
        document.getElementById("aaresult").innerHTML="<div class=\'alert alert-"+(j.ok?"success":"danger")+"\'>"+j.message+"</div>";});}</script>';
}

function step_whatsapp(): string
{
    $w = $_SESSION['install']['whatsapp'] ?? ['api_url' => 'https://bulk.akdwk.in/api.php', 'api_key' => '7016034943', 'session_id' => '9978123146'];
    return '<h2>WhatsApp Setup</h2>
    <form method="post" action="?step=8&action=save" id="waform">
      <div class="form-group"><label>API URL</label><input name="api_url" value="' . h($w['api_url']) . '" required></div>
      <div class="form-group"><label>API Key</label><input name="api_key" value="' . h($w['api_key']) . '" required></div>
      <div class="form-group"><label>Session ID</label><input name="session_id" value="' . h($w['session_id']) . '" required></div>
      <div class="form-group"><label>Test number (તમારો WhatsApp)</label><input name="test_number" placeholder="9876543210"></div>
      <div class="flex gap-2">
        <button type="button" class="btn btn-outline" onclick="testWa()">Send Test Message</button>
        <button type="submit" class="btn btn-primary">આગળ →</button>
      </div>
      <div id="waresult" class="mt-2"></div>
    </form>
    <script>function testWa(){var d=new FormData(document.getElementById("waform"));
      fetch("?action=test_whatsapp",{method:"POST",body:d}).then(r=>r.json()).then(j=>{
        document.getElementById("waresult").innerHTML="<div class=\'alert alert-"+(j.ok?"success":"danger")+"\'>"+j.message+"</div>";});}</script>';
}

function step_cron(): string
{
    $php = detect_php_bin();
    $base = dirname(__DIR__);
    $cmd = "*/5 * * * * {$php} {$base}/cron/cron.php >/dev/null 2>&1";
    return '<h2>Cron Setup</h2>
    <p class="muted">આ line તમારા server ના crontab માં ઉમેરો (aaPanel → Cron પણ ચાલે):</p>
    <div class="card"><div class="card-body mono" id="cron" style="word-break:break-all">' . h($cmd) . '</div></div>
    <button class="btn btn-outline" onclick="navigator.clipboard.writeText(document.getElementById(\'cron\').innerText)">Copy</button>
    <form method="post" action="?step=9&action=save" style="display:inline">
      <button type="submit" class="btn btn-primary">આગળ →</button>
    </form>';
}

function step_finish(): string
{
    return '<h2>પૂરું કરો</h2>
    <p>હવે installer .env + config/config.php generate કરશે, super admin બનાવશે, aaPanel server register કરશે અને installer lock કરશે.</p>
    <form method="post" action="?step=10&action=save">
      <button type="submit" class="btn btn-primary">Install પૂરું કરો ✅</button>
    </form>';
}

function step_done(): string
{
    return '<h2>🎉 Install પૂરું થયું!</h2>
    <div class="alert alert-success">AK Cloud તૈયાર છે. Installer હવે lock થઈ ગયું છે.</div>
    <p>⚠️ સુરક્ષા માટે <code>/install</code> folder delete કરી શકો છો.</p>
    <a class="btn btn-primary" href="' . rtrim(base_url(), '/') . '/login">Admin લોગિન →</a>';
}

function installer_page(string $title, string $body): string
{
    $css = base_url() . '/public/assets/css/app.css';
    return '<!doctype html><html lang="gu"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>' . h($title) . ' — AK Cloud Installer</title>
    <link rel="stylesheet" href="' . h($css) . '">
    <style>.stepper{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px}
    .step{flex:1;min-width:70px;text-align:center;font-size:.72rem;padding:8px 4px;border-radius:8px;background:var(--surface);border:1px solid var(--border);color:var(--muted)}
    .step span{display:block;font-weight:700;font-size:1rem}
    .step.active{background:var(--primary);color:#fff;border-color:var(--primary)}
    .step.done{background:rgba(22,163,74,.15);color:var(--success);border-color:transparent}
    .wrap{max-width:760px;margin:30px auto;padding:0 16px}</style></head>
    <body><div class="wrap"><div class="card"><div class="card-body">' . $body . '</div></div>
    <p class="text-center muted small mt-3">AK Cloud Installer</p></div></body></html>';
}

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
