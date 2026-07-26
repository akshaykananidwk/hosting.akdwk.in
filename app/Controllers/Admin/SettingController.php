<?php
// FILE: /app/Controllers/Admin/SettingController.php
// -------------------------------------------------------------------
// Admin — System settings (સેટિંગ્સ). Tabbed form driven by a schema.
// Secrets are stored encrypted and only overwritten when a new value
// is submitted (blank keeps the existing secret).
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class SettingController extends Controller
{
    /** Tabbed settings form. */
    public function index(Request $request, string $id = ''): Response
    {
        return $this->view('admin.settings.index', [
            'schema' => $this->schema(),
        ]);
    }

    /** Persist all submitted settings. */
    public function save(Request $request, string $id = ''): Response
    {
        $input = (array) $request->input('s', []);

        foreach ($this->schema() as $tab) {
            foreach ($tab['fields'] as $f) {
                $group = $f['group'];
                $key = $f['key'];
                $full = $group . '.' . $key;
                $type = $f['type'];

                if ($type === 'secret') {
                    $val = trim((string) ($input[$full] ?? ''));
                    if ($val !== '') {
                        setting_set($key, $val, $group, true);
                    }
                    // Empty → keep existing encrypted value.
                    continue;
                }
                if ($type === 'bool') {
                    setting_set($key, isset($input[$full]) ? '1' : '0', $group, false);
                    continue;
                }
                $val = $input[$full] ?? '';
                setting_set($key, (string) $val, $group, false);
            }
        }

        audit('settings.update');
        return back_with('success', 'સેટિંગ્સ સેવ થઈ ✅');
    }

    /**
     * Declarative settings schema — tabs → fields.
     * type: text | number | textarea | bool | secret | select
     */
    private function schema(): array
    {
        return [
            [
                'id' => 'general', 'label' => 'General',
                'fields' => [
                    ['group' => 'general', 'key' => 'company_name', 'label' => 'Company Name', 'type' => 'text'],
                    ['group' => 'general', 'key' => 'app_url', 'label' => 'App URL', 'type' => 'text'],
                    ['group' => 'general', 'key' => 'timezone', 'label' => 'Timezone', 'type' => 'text'],
                    ['group' => 'general', 'key' => 'currency', 'label' => 'Currency', 'type' => 'text'],
                    ['group' => 'general', 'key' => 'currency_symbol', 'label' => 'Currency Symbol', 'type' => 'text'],
                    ['group' => 'general', 'key' => 'default_language', 'label' => 'Default Language', 'type' => 'select', 'options' => ['gu' => 'ગુજરાતી', 'en' => 'English', 'hi' => 'हिंदी']],
                    ['group' => 'general', 'key' => 'maintenance_mode', 'label' => 'Maintenance Mode', 'type' => 'bool'],
                    ['group' => 'billing', 'key' => 'invoice_prefix', 'label' => 'Invoice Prefix', 'type' => 'text'],
                    ['group' => 'billing', 'key' => 'invoice_generate_days', 'label' => 'Generate Invoice (days before due)', 'type' => 'number'],
                    ['group' => 'billing', 'key' => 'grace_days', 'label' => 'Grace Days (before suspend)', 'type' => 'number'],
                    ['group' => 'billing', 'key' => 'late_fee', 'label' => 'Late Fee', 'type' => 'number'],
                ],
            ],
            [
                'id' => 'tax', 'label' => 'Tax / GST',
                'fields' => [
                    ['group' => 'tax', 'key' => 'gst_enabled', 'label' => 'GST Enabled', 'type' => 'bool'],
                    ['group' => 'tax', 'key' => 'gst_percent', 'label' => 'GST %', 'type' => 'number'],
                    ['group' => 'tax', 'key' => 'company_gstin', 'label' => 'Company GSTIN', 'type' => 'text'],
                    ['group' => 'tax', 'key' => 'company_state_code', 'label' => 'Company State Code', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'whatsapp', 'label' => 'WhatsApp',
                'fields' => [
                    ['group' => 'whatsapp', 'key' => 'api_url', 'label' => 'API URL', 'type' => 'text'],
                    ['group' => 'whatsapp', 'key' => 'api_key', 'label' => 'API Key', 'type' => 'secret'],
                    ['group' => 'whatsapp', 'key' => 'session_id', 'label' => 'Session ID', 'type' => 'secret'],
                    ['group' => 'whatsapp', 'key' => 'hourly_max', 'label' => 'Hourly Max', 'type' => 'number'],
                    ['group' => 'whatsapp', 'key' => 'daily_max', 'label' => 'Daily Max', 'type' => 'number'],
                    ['group' => 'whatsapp', 'key' => 'delay_min', 'label' => 'Delay Min (sec)', 'type' => 'number'],
                    ['group' => 'whatsapp', 'key' => 'delay_max', 'label' => 'Delay Max (sec)', 'type' => 'number'],
                ],
            ],
            [
                'id' => 'smtp', 'label' => 'SMTP',
                'fields' => [
                    ['group' => 'smtp', 'key' => 'host', 'label' => 'Host', 'type' => 'text'],
                    ['group' => 'smtp', 'key' => 'port', 'label' => 'Port', 'type' => 'number'],
                    ['group' => 'smtp', 'key' => 'username', 'label' => 'Username', 'type' => 'text'],
                    ['group' => 'smtp', 'key' => 'password', 'label' => 'Password', 'type' => 'secret'],
                    ['group' => 'smtp', 'key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None']],
                    ['group' => 'smtp', 'key' => 'from_email', 'label' => 'From Email', 'type' => 'text'],
                    ['group' => 'smtp', 'key' => 'from_name', 'label' => 'From Name', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'updates', 'label' => 'Updates',
                'fields' => [
                    ['group' => 'updates', 'key' => 'repo', 'label' => 'GitHub Repo (owner/name)', 'type' => 'text'],
                    ['group' => 'updates', 'key' => 'branch', 'label' => 'Branch', 'type' => 'text'],
                    ['group' => 'updates', 'key' => 'github_token', 'label' => 'GitHub Token', 'type' => 'secret'],
                    ['group' => 'updates', 'key' => 'auto_check', 'label' => 'Auto Check', 'type' => 'bool'],
                ],
            ],
            [
                'id' => 'isolation', 'label' => 'Isolation / Quota',
                'fields' => [
                    ['group' => 'isolation', 'key' => 'enabled', 'label' => 'Isolation Enabled', 'type' => 'bool'],
                    ['group' => 'isolation', 'key' => 'use_aapanel_builtin', 'label' => 'Use aaPanel Built-in', 'type' => 'bool'],
                    ['group' => 'isolation', 'key' => 'disabled_functions', 'label' => 'Disabled PHP Functions', 'type' => 'textarea'],
                    ['group' => 'quota', 'key' => 'disk_grace_days', 'label' => 'Disk Grace Days', 'type' => 'number'],
                    ['group' => 'quota', 'key' => 'use_setquota', 'label' => 'Use setquota', 'type' => 'bool'],
                    ['group' => 'quota', 'key' => 'warn_thresholds', 'label' => 'Warn Thresholds (%)', 'type' => 'text'],
                ],
            ],
        ];
    }
}
