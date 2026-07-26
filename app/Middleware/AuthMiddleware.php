<?php
// FILE: /app/Middleware/AuthMiddleware.php
// -------------------------------------------------------------------
// Logged-in users only. Guests are redirected to login (or JSON 401).
// -------------------------------------------------------------------

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (auth()->guest()) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Unauthenticated'], 401);
            }
            Session::flash('intended', $request->path());
            return Response::redirect(url('login'));
        }
        return $next($request);
    }
}
