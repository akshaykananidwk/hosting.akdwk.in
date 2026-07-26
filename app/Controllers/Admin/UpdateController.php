<?php
// FILE: /app/Controllers/Admin/UpdateController.php
// -------------------------------------------------------------------
// Admin — GitHub auto-update: version, check, run (super_admin only).
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\UpdateService;

class UpdateController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $service = new UpdateService();
        $history = db()->table('update_history')
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return $this->view('admin.updates.index', [
            'currentVersion' => $service->currentVersion(),
            'history' => $history,
            'canRun' => auth()->is('super_admin'),
        ]);
    }

    public function check(Request $request, string $id = ''): Response
    {
        return $this->json((new UpdateService())->checkForUpdate());
    }

    public function run(Request $request, string $id = ''): Response
    {
        if (!auth()->is('super_admin')) {
            abort(403, 'ફક્ત super_admin update ચલાવી શકે.');
        }

        $result = (new UpdateService())->runUpdate();
        audit('system.update', 'update_history', null, [
            'ok' => $result['ok'] ?? false,
            'message' => $result['message'] ?? '',
        ]);

        return $this->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ]);
    }
}
