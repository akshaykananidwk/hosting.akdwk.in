<?php
// FILE: /app/Controllers/Admin/LogController.php
// -------------------------------------------------------------------
// Admin — unified log viewer (aaPanel / cron / login / audit) tabs.
// -------------------------------------------------------------------

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class LogController extends Controller
{
    /** Allowed tabs mapped to their backing table. */
    private const TABS = [
        'aapanel' => 'aapanel_logs',
        'cron' => 'cron_logs',
        'login' => 'login_logs',
        'audit' => 'audit_logs',
    ];

    public function index(Request $request, string $id = ''): Response
    {
        $tab = (string) $request->query('tab', 'aapanel');
        if (!isset(self::TABS[$tab])) {
            $tab = 'aapanel';
        }
        $table = self::TABS[$tab];

        $rows = db()->table($table)
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        return $this->view('admin.logs.index', [
            'tab' => $tab,
            'tabs' => array_keys(self::TABS),
            'rows' => $rows,
        ]);
    }
}
