<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the interest loop without deleting it.
 *
 * The routes stay registered, so flag-on installs keep the exact behaviour
 * they had. When the flag is off an interest URL is indistinguishable from a
 * URL that never existed.
 */
class EnsureInterestsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('marketplace.interests_enabled'), 404);

        return $next($request);
    }
}
