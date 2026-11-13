<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceUiEnabled
{
    /**
     * Keep legacy team and developer routes callable only when the advanced
     * workspace interface is intentionally enabled.
     *
     * A bookmarked page leads consumers back to their account settings.
     * Mutations and JSON endpoints are not made available by knowing the URL.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('features.workspace.ui', false)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect('/settings/profile');
        }

        abort(404);
    }
}
