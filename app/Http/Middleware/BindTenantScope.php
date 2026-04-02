<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Load once — hasPermission() uses this in-memory, no N+1 per gate check
            $user->loadMissing('role.permissions');
            app()->instance('tenant.customer_id', $user->customer_id);
        } else {
            app()->instance('tenant.customer_id', null);
        }

        return $next($request);
    }
}
