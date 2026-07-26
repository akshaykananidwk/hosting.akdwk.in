<?php
// FILE: /app/Middleware/RoleMiddleware.php
// -------------------------------------------------------------------
// Type/role guard. Router aliases: 'admin', 'reseller', 'client'.
// Usage: group middleware ['auth','admin'].
// -------------------------------------------------------------------

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

abstract class RoleMiddleware
{
    /** @var string[] allowed user types */
    protected array $allowed = [];

    public function handle(Request $request, \Closure $next): Response
    {
        $type = auth()->user()['type'] ?? null;
        if ($type === null) {
            return Response::redirect(url('login'));
        }
        if (!in_array($type, $this->allowed, true)) {
            throw new HttpException(403, 'You do not have access to this area.');
        }
        return $next($request);
    }
}
