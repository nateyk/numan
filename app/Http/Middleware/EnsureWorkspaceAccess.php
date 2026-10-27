<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $workspace = $user->currentWorkspace;

        // If no current workspace, fall back to the personal one, then to any
        // workspace the user belongs to. An invited member with no current
        // workspace must land in the team they were invited to, not in a brand
        // new personal workspace.
        if (! $workspace) {
            $workspace = $user->personalWorkspace()
                ?? $user->workspaces()->first();

            if (! $workspace) {
                // Every authenticated surface needs a workspace, including the
                // ones a user reaches before finishing onboarding. Creating one
                // here is the backstop for accounts made by a factory, a seeder,
                // or a path that predates personal workspace creation.
                $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
            }

            $user->switchWorkspace($workspace);
        }

        // Ensure user belongs to the current workspace
        if (! $workspace->hasUser($user)) {
            // Try to find another workspace the user belongs to
            $anotherWorkspace = $user->workspaces()->first();

            if (! $anotherWorkspace) {
                abort(403, 'You do not have access to any workspace.');
            }

            $workspace = $anotherWorkspace;
            $user->switchWorkspace($workspace);
        }

        // switchWorkspace() writes the id but leaves any already-loaded
        // currentWorkspace relation pointing at the old value. The controller and
        // the shared Inertia props read that relation.
        $user->setRelation('currentWorkspace', $workspace);

        // Share current workspace with views/Inertia
        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
