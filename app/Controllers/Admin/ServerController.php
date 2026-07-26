<?php
// FILE: /app/Controllers/Admin/ServerController.php
// -------------------------------------------------------------------
// Admin — aaPanel servers CRUD + live connection test + site import.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AaPanelService;

class ServerController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $servers = db()->table('servers')->orderBy('name', 'asc')->get();
        return $this->view('admin.servers.index', ['servers' => $servers]);
    }

    public function create(Request $request, string $id = ''): Response
    {
        return $this->view('admin.servers.create');
    }

    public function store(Request $request, string $id = ''): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|max:120',
            'panel_url' => 'required|url|max:191',
            'api_key' => 'required',
        ]);

        $newId = db()->table('servers')->insert([
            'tenant_id' => auth()->tenantId() ?? 1,
            'name' => $data['name'],
            'hostname' => $request->input('hostname') ?: null,
            'panel_url' => rtrim((string) $data['panel_url'], '/'),
            'api_key' => encrypt((string) $data['api_key']),
            'ip_address' => $request->input('ip_address') ?: null,
            'type' => 'aapanel',
            'verify_ssl' => $request->boolean('verify_ssl') ? 1 : 0,
            'default_php' => (string) $request->input('default_php', '82'),
            'max_accounts' => (int) $request->input('max_accounts', 150),
            'auto_assign' => $request->boolean('auto_assign') ? 1 : 0,
            'status' => 'online',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        audit('server.create', 'server', $newId, ['name' => $data['name']]);
        return redirect_route('admin/servers', 'success', 'Server ઉમેરાયો.');
    }

    public function test(Request $request, string $id = ''): Response
    {
        $server = db()->table('servers')->where('id', (int) $id)->first();
        if (!$server) {
            return $this->json(['ok' => false, 'error' => 'Server મળ્યો નથી.'], 404);
        }

        try {
            $res = AaPanelService::forServer($server)->testConnection();
        } catch (\Throwable $e) {
            db()->table('servers')->where('id', (int) $id)
                ->update(['status' => 'offline', 'last_health_at' => now(), 'updated_at' => now()]);
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        $sys = is_array($res['data'] ?? null) ? $res['data'] : [];
        $update = [
            'status' => $res['ok'] ? 'online' : 'offline',
            'last_health_at' => now(),
            'updated_at' => now(),
        ];
        if (isset($sys['memTotal'], $sys['memRealUsed']) && (float) $sys['memTotal'] > 0) {
            $update['ram_percent'] = round((float) $sys['memRealUsed'] / (float) $sys['memTotal'] * 100, 2);
        }
        if (isset($sys['cpuRealUsed'])) {
            $update['cpu_percent'] = round((float) $sys['cpuRealUsed'], 2);
        }
        db()->table('servers')->where('id', (int) $id)->update($update);

        return $this->json([
            'ok' => (bool) $res['ok'],
            'data' => $res['data'] ?? null,
            'error' => $res['error'] ?? null,
        ]);
    }

    public function importSites(Request $request, string $id = ''): Response
    {
        $server = db()->table('servers')->where('id', (int) $id)->first();
        if (!$server) {
            return $this->json(['ok' => false, 'error' => 'Server મળ્યો નથી.'], 404);
        }

        try {
            $raw = AaPanelService::forServer($server)->listSites(1, 200);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        $list = is_array($raw['data'] ?? null) ? $raw['data'] : (is_array($raw) ? $raw : []);
        $sites = [];
        foreach ($list as $s) {
            if (!is_array($s)) {
                continue;
            }
            $sites[] = [
                'id' => $s['id'] ?? null,
                'name' => (string) ($s['name'] ?? ''),
                'path' => (string) ($s['path'] ?? ''),
                'status' => (string) ($s['status'] ?? ''),
                'php' => (string) ($s['php_version'] ?? ($s['php'] ?? '')),
            ];
        }

        return $this->json([
            'ok' => true,
            'count' => count($sites),
            'sites' => $sites,
            'note' => 'આ ફક્ત listing tool છે — હાલ mapping નથી થતું. પછીથી map કરી શકાશે.',
        ]);
    }
}
