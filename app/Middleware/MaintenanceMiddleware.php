<?php
// FILE: /app/Middleware/MaintenanceMiddleware.php
// -------------------------------------------------------------------
// Maintenance mode — updater ON કરે ત્યારે clients ને maintenance page
// બતાવે; super admin ને પસાર થવા દે.
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
                : '<h1>🛠️ જાળવણી ચાલુ છે</h1><p>સિસ્ટમ થોડી વારમાં પાછી આવશે. કૃપા કરી થોડી વાર પછી પ્રયત્ન કરો.</p>';
            return Response::make($html, 503)->header('Retry-After', '120');
        }
        return $next($request);
    }
}
