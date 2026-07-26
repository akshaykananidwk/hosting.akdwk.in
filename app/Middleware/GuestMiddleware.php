<?php
// FILE: /app/Middleware/GuestMiddleware.php
// -------------------------------------------------------------------
// ફક્ત guests માટે (login/register pages). પહેલેથી logged-in હોય તો
// dashboard પર મોકલે.
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
