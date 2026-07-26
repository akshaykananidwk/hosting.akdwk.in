<?php
// FILE: /app/Controllers/Client/FileManagerController.php
// -------------------------------------------------------------------
// MODULE 10 — Lightweight web file manager proxying aaPanel's GetDir/
// SaveFileBody. Every path must stay inside service.site_path — any
// attempt to escape it returns 403.
// -------------------------------------------------------------------

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AaPanelService;

class FileManagerController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $service  = $this->ownedService($id);
        $sitePath = $this->sitePath($service);
        $path     = trim((string) $request->query('path', $sitePath));
        if ($path === '') {
            $path = $sitePath;
        }
        $this->guardPath($path, $sitePath);

        $dirs  = [];
        $files = [];
        $error = null;

        $server = $service['server_id']
            ? db()->table('servers')->where('id', (int) $service['server_id'])->first()
            : null;

        if ($server) {
            $res  = AaPanelService::forServer($server)->getDir($path);
            $data = is_array($res['data'] ?? null) ? $res['data'] : [];
            foreach ((array) ($data['DIR'] ?? []) as $row) {
                $parts  = explode(';', (string) $row);
                $dirs[] = ['name' => $parts[0] ?? '', 'raw' => (string) $row];
            }
            foreach ((array) ($data['FILES'] ?? []) as $row) {
                $parts   = explode(';', (string) $row);
                $files[] = ['name' => $parts[0] ?? '', 'size' => $parts[1] ?? '', 'raw' => (string) $row];
            }
            if (!($res['status'] ?? false) && !$dirs && !$files) {
                $error = $res['error'] ?? 'Could not connect to the file manager.';
            }
        } else {
            $error = 'No server has been assigned to this service yet.';
        }

        return $this->view('client.files.index', [
            'service'  => $service,
            'sitePath' => $sitePath,
            'path'     => $path,
            'dirs'     => $dirs,
            'files'    => $files,
            'error'    => $error,
        ]);
    }

    public function save(Request $request, string $id = ''): Response
    {
        $service  = $this->ownedService($id);
        $sitePath = $this->sitePath($service);
        $data     = $this->validate($request, ['path' => 'required', 'data' => 'required']);
        $path     = trim((string) $data['path']);
        $this->guardPath($path, $sitePath);

        $server = $service['server_id']
            ? db()->table('servers')->where('id', (int) $service['server_id'])->first()
            : null;

        if (!$server) {
            return back_with('error', 'No server assigned — the file was not saved.');
        }

        AaPanelService::forServer($server)->saveFileBody($path, (string) $data['data']);
        audit('client.files.save', 'service', (int) $service['id'], ['path' => $path]);

        return back_with('success', 'File saved.');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function sitePath(array $service): string
    {
        return (string) ($service['site_path'] ?: ('/www/wwwroot/' . $service['domain']));
    }

    /**
     * Any requested path MUST start with the service site_path and may
     * not contain traversal (..). Otherwise 403 — no escaping the jail.
     */
    private function guardPath(string $path, string $sitePath): void
    {
        $ok = $path !== '' && !str_contains($path, '..') && str_starts_with($path, $sitePath);
        $this->authorize($ok, 403, 'You do not have access to this path.');
    }

    private function clientId(): int
    {
        $client = db()->table('clients')->where('user_id', auth()->id())->first();
        return (int) ($client['id'] ?? 0);
    }

    private function ownedService(string $id): array
    {
        $clientId = $this->clientId();
        $service  = db()->table('services')->where('id', (int) $id)->first();
        $this->authorize($clientId > 0 && $service !== null && (int) $service['client_id'] === $clientId, 403);
        /** @var array $service */
        return $service;
    }
}
