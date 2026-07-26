<?php
// FILE: /app/Middleware/GuestMiddleware.php
// -------------------------------------------------------------------
// Guests only (login/register pages). Already-authenticated users are
// sent to their dashboard.
// -------------------------------------------------------------------

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class GuestMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (auth()->check()) {
            return Response::redirect(url(dashboard_path()));
        }
        return $next($request);
    }
}
