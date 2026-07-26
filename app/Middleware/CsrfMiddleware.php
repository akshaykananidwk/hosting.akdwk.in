<?php
// FILE: /app/Middleware/CsrfMiddleware.php
// -------------------------------------------------------------------
// CSRF protection: every state-changing request (POST/PUT/PATCH/DELETE)
// must carry a valid `_token` field or X-CSRF-TOKEN header.
// -------------------------------------------------------------------

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class CsrfMiddleware
{
    /** URIs that skip CSRF (e.g. gateway webhooks with signature auth). */
    protected array $except = [
        '/api/',            // API uses token auth, not session CSRF
        '/webhook/',        // payment gateway callbacks (signature verified)
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || $this->isExcluded($request)) {
            return $next($request);
        }

        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
        if (!Session::verifyToken(is_string($token) ? $token : null)) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'CSRF token mismatch'], 419);
            }
            throw new HttpException(419, 'Security token mismatch. Please refresh the page and try again.');
        }
        return $next($request);
    }

    protected function isExcluded(Request $request): bool
    {
        foreach ($this->except as $prefix) {
            if (str_starts_with($request->path(), $prefix)) {
                return true;
            }
        }
        return false;
    }
}
