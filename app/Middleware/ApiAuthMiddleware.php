<?php
// FILE: /app/Middleware/ApiAuthMiddleware.php
// -------------------------------------------------------------------
// REST API token auth (Module 20). Bearer token → api_keys table
// (hashed). Rate-limit + last_used update. Sets api context.
// -------------------------------------------------------------------

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class ApiAuthMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return Response::json(['error' => 'Missing API token'], 401);
        }
        $hash = hash('sha256', $token);
        $key = db()->table('api_keys')
            ->where('token_hash', $hash)
            ->where('status', 'active')
            ->first();

        if (!$key) {
            return Response::json(['error' => 'Invalid API token'], 401);
        }
        if (!empty($key['expires_at']) && strtotime($key['expires_at']) < time()) {
            return Response::json(['error' => 'API token expired'], 401);
        }

        // Simple per-key rate limit (per minute) using file cache.
        $bucket = 'api_rl_' . $key['id'] . '_' . date('YmdHi');
        $count = cache()->increment($bucket);
        cache()->put($bucket, $count, 70);
        if ($count > (int) ($key['rate_limit'] ?? 60)) {
            return Response::json(['error' => 'Rate limit exceeded'], 429);
        }

        db()->table('api_keys')->where('id', (int) $key['id'])->update([
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        // Expose the resolved key + user for controllers.
        $request->setAttribute('api_key', $key);
        if (!empty($key['user_id'])) {
            auth()->loginUsingId((int) $key['user_id']);
        }

        return $next($request);
    }
}
