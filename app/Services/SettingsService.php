<?php
// FILE: /app/Services/SettingsService.php
// -------------------------------------------------------------------
// DB-driven settings (Module 21). `settings` table માંથી key/value
// વાંચે, encrypted values decrypt કરે, અને per-request cache રાખે.
// Install પહેલા DB ન હોય તો gracefully default આપે.
// -------------------------------------------------------------------

namespace App\Services;

use App\Core\App;

class SettingsService
{
    /** @var array<string,mixed>|null  loaded once per request */
    protected ?array $cache = null;

    /**
     * Load all settings into memory (group.key => value). Encrypted
     * rows are decrypted on read.
     */
    protected function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $this->cache = [];
        try {
            $rows = App::instance()->make('db')->table('settings')->get();
            foreach ($rows as $row) {
                $value = $row['value'];
                if ((int) $row['is_encrypted'] === 1 && $value !== null && $value !== '') {
                    $value = App::instance()->make('crypt')->decrypt($value) ?? '';
                }
                $this->cache[$row['group'] . '.' . $row['key']] = $value;
                // Also allow lookup by bare key (last one wins).
                $this->cache[$row['key']] = $value;
            }
        } catch (\Throwable) {
            // settings table not ready (pre-install) — leave cache empty.
        }
        return $this->cache;
    }

    /**
     * Get a setting by "group.key" or bare "key".
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->load();
        $value = $data[$key] ?? $default;
        return ($value === null || $value === '') && $default !== null ? $default : $value;
    }

    public function all(): array
    {
        return $this->load();
    }

    /**
     * Persist a setting (insert or update), optionally encrypted.
     */
    public function set(string $key, mixed $value, string $group = 'general', bool $encrypted = false): void
    {
        $db = App::instance()->make('db');
        // Support "group.key" shorthand.
        if (str_contains($key, '.')) {
            [$group, $key] = explode('.', $key, 2);
        }
        $store = (string) $value;
        if ($encrypted && $store !== '') {
            $store = App::instance()->make('crypt')->encrypt($store);
        }
        $existing = $db->table('settings')
            ->whereNull('tenant_id')
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if ($existing) {
            $db->table('settings')->where('id', (int) $existing['id'])->update([
                'value' => $store,
                'is_encrypted' => $encrypted ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('settings')->insert([
                'tenant_id' => null,
                'group' => $group,
                'key' => $key,
                'value' => $store,
                'is_encrypted' => $encrypted ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        // Refresh in-memory cache.
        $this->cache = null;
    }

    public function forgetCache(): void
    {
        $this->cache = null;
    }

    public function isMaintenanceMode(): bool
    {
        return (string) $this->get('general.maintenance_mode', '0') === '1';
    }
}
