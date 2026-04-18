<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isClientUser() && !$user->customer?->is_active) {
            $user->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Your company account has been deactivated. Please contact your administrator.',
            ], 403);
        }

        return $next($request);
    }
}
