<?php
// FILE: /app/Controllers/Admin/BackupController.php
// -------------------------------------------------------------------
// Admin — system backups list + on-demand full backup.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BackupService;

class BackupController extends Controller
{
    public function index(Request $request, string $id = ''): Response
    {
        $backups = db()->table('backups')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return $this->view('admin.backups.index', ['backups' => $backups]);
    }

    public function run(Request $request, string $id = ''): Response
    {
        try {
            $path = (new BackupService())->fullBackup('manual');
        } catch (\Throwable $e) {
            return back_with('error', 'Backup failed: ' . $e->getMessage());
        }

        if ($path === null) {
            return back_with('error', 'Backup failed (check integrity and disk space).');
        }

        audit('backup.run', 'backup', null, ['path' => basename($path)]);
        return back_with('success', 'Backup created successfully: ' . basename($path));
    }
}
