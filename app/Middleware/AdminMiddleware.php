<?php
// FILE: /app/Middleware/AdminMiddleware.php
// -------------------------------------------------------------------
// Admin area guard — super_admin / tenant_owner / staff.
// -------------------------------------------------------------------

namespace App\Middleware;

class AdminMiddleware extends RoleMiddleware
{
    protected array $allowed = ['super_admin', 'tenant_owner', 'staff'];
}
