<?php
// FILE: /app/Middleware/ClientMiddleware.php
// -------------------------------------------------------------------
// Client area guard.
// -------------------------------------------------------------------

namespace App\Middleware;

class ClientMiddleware extends RoleMiddleware
{
    protected array $allowed = ['client'];
}
