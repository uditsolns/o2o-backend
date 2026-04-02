<?php

namespace App\Http\Middleware;

use App\Enums\CustomerOnboardingStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isPlatformUser()) {
            return $next($request);
        }

        if ($user->customer?->onboarding_status !== CustomerOnboardingStatus::Completed) {
            return response()->json(['message' => 'Account onboarding is not complete.'], 403);
        }

        return $next($request);
    }
}
