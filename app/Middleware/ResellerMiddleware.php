<?php
// FILE: /app/Middleware/ResellerMiddleware.php
// -------------------------------------------------------------------
// Reseller area guard.
// -------------------------------------------------------------------

namespace App\Middleware;

class ResellerMiddleware extends RoleMiddleware
{
    protected array $allowed = ['reseller', 'super_admin', 'tenant_owner'];
}
