<?php
// FILE: /app/Services/IsolationService.php
// -------------------------------------------------------------------
// MODULE 5(c) — Per-site user isolation. aaPanel default માં બધી sites
// એક જ `www` user નીચે ચાલે — shared hosting માટે ખતરનાક. આ service
// per-site Linux user + PHP-FPM pool + disabled_functions apply કરે.
// બધા shell commands escapeshellarg() સાથે.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class IsolationService
{
    /**
     * Apply isolation for a service. Returns [ok, message].
     * settings('isolation.enabled') OFF હોય તો skip.
     * aaPanel PRO built-in isolation વાપરવું હોય તો
     * settings('isolation.use_aapanel_builtin')=1.
     */
    public function apply(array $service, string $phpVersion = '82'): array
    {
        if ((string) settings('isolation.enabled', '1') !== '1') {
            return [true, 'isolation disabled in settings'];
        }
        if (!function_exists('shell_exec') || $this->isDisabled('shell_exec')) {
            return [false, 'shell_exec disabled — panel site પર enable કરો'];
        }

        $sitePath = $service['site_path'] ?? ('/www/wwwroot/' . $service['domain']);
        $user = $this->userName($service);
        $pool = $this->poolName($service);

        // 1) Create the Linux user (no shell, no login).
        $this->run('id -u ' . escapeshellarg($user) . ' >/dev/null 2>&1 || useradd -M -s /usr/sbin/nologin ' . escapeshellarg($user));

        // 2) chown the site directory to the isolated user.
        $this->run('chown -R ' . escapeshellarg($user) . ':' . escapeshellarg($user) . ' ' . escapeshellarg($sitePath));

        // 3) Generate a dedicated PHP-FPM pool.
        $poolFile = "/www/server/php/{$phpVersion}/etc/php-fpm.d/{$pool}.conf";
        $socket = "/tmp/php-cgi-{$pool}.sock";
        $disabled = (string) settings('isolation.disabled_functions', 'exec,shell_exec,system,passthru,proc_open,popen,symlink,link,putenv,pcntl_exec,dl');
        $poolConf = $this->buildPoolConfig($pool, $user, $socket, $sitePath, $disabled);
        $this->writeFile($poolFile, $poolConf);

        // 4) Reload PHP-FPM.
        $this->run("/etc/init.d/php-fpm-{$phpVersion} reload 2>/dev/null || systemctl reload php-fpm-{$phpVersion} 2>/dev/null");

        // 5) Record on the service_details row.
        App::instance()->make('db')->table('service_details')
            ->where('service_id', (int) $service['id'])
            ->update([
                'fpm_pool' => $pool,
                'linux_user' => $user,
                'isolation_applied' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return [true, "isolation applied (user={$user}, pool={$pool}, socket={$socket})"];
    }

    /**
     * Remove isolation artifacts (on terminate).
     */
    public function remove(array $service, string $phpVersion = '82'): void
    {
        if (!function_exists('shell_exec') || $this->isDisabled('shell_exec')) {
            return;
        }
        $pool = $this->poolName($service);
        $user = $this->userName($service);
        @unlink("/www/server/php/{$phpVersion}/etc/php-fpm.d/{$pool}.conf");
        $this->run("/etc/init.d/php-fpm-{$phpVersion} reload 2>/dev/null || systemctl reload php-fpm-{$phpVersion} 2>/dev/null");
        $this->run('userdel ' . escapeshellarg($user) . ' 2>/dev/null');
    }

    protected function buildPoolConfig(string $pool, string $user, string $socket, string $sitePath, string $disabled): string
    {
        return <<<CONF
[{$pool}]
user = {$user}
group = {$user}
listen = {$socket}
listen.owner = www
listen.group = www
listen.mode = 0660
pm = ondemand
pm.max_children = 20
pm.process_idle_timeout = 10s
pm.max_requests = 500
php_admin_value[open_basedir] = {$sitePath}/:/tmp/:/proc/
php_admin_value[disable_functions] = {$disabled}
php_admin_value[upload_tmp_dir] = {$sitePath}/tmp
php_admin_value[session.save_path] = {$sitePath}/tmp
CONF;
    }

    protected function userName(array $service): string
    {
        return substr('akc' . (int) $service['id'], 0, 32);
    }

    protected function poolName(array $service): string
    {
        return 'akc' . (int) $service['id'];
    }

    protected function writeFile(string $path, string $content): void
    {
        $tmp = App::instance()->storagePath('temp/' . basename($path) . '.' . uniqid());
        @file_put_contents($tmp, $content);
        $this->run('mv ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path));
    }

    protected function run(string $cmd): string
    {
        return (string) @shell_exec($cmd . ' 2>&1');
    }

    protected function isDisabled(string $func): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return in_array($func, $disabled, true);
    }
}
