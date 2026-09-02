<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsOnboarded
{
    /**
     * Handle an incoming request.
     *
     * NumanFreight public profile is source of truth, but legacy workspace onboarded_at
     * is kept for backwards compatibility with existing SAAS Starter data.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $isLegacyOnboarded = ! is_null($user->onboarded_at);
        $isProfileOnboarded = $user->isProfileOnboarded();

        if (! $isLegacyOnboarded && ! $isProfileOnboarded) {
            // Not onboarded by either system — ensure we don't redirect the onboarding routes themselves
            if ($request->routeIs('onboarding.*')) {
                return $next($request);
            }

            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}
