<?php
// FILE: /app/Middleware/MaintenanceMiddleware.php
// -------------------------------------------------------------------
// Maintenance mode: while the updater has it on, clients see the
// maintenance page; super admins are allowed through.
// -------------------------------------------------------------------

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class MaintenanceMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (settings()->isMaintenanceMode() && !auth()->is('super_admin')) {
            $html = view_exists('errors.maintenance')
                ? view('errors.maintenance')
                : '<h1>🛠️ Under maintenance</h1><p>The system will be back shortly. Please try again in a few minutes.</p>';
            return Response::make($html, 503)->header('Retry-After', '120');
        }
        return $next($request);
    }
}
