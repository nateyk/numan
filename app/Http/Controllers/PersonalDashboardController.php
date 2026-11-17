<?php

namespace App\Http\Controllers;

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalDashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Keep the existing individual redirect when the dormant workspace UI is enabled.
        if (config('features.workspace.ui') && $user->isIndividual()) {
            return redirect()->route('freight.discovery');
        }

        $workspace = $user->currentWorkspace;
        $props = [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'plan' => $workspace->plan_name,
                'members_count' => $workspace->users()->count(),
            ],
        ];

        if (! config('features.workspace.ui')) {
            $owned = FreightPost::query()->ownedBy($user);

            // These match My Freight's owner-scoped status filters, including
            // hidden posts that the owner is still entitled to manage.
            $props['freightStats'] = [
                'total' => (clone $owned)->count(),
                'active' => (clone $owned)->where('status', FreightPostStatus::Active->value)->count(),
                'drafts' => (clone $owned)->where('status', FreightPostStatus::Draft->value)->count(),
                'arrived' => (clone $owned)->whereNotNull('arrived_at')->count(),
            ];

            // Do not serialize entire FreightPost models, contact data, or
            // another account's posts onto the dashboard.
            // The unfiltered public board uses these same visibility scopes.
            $props['availableFreight'] = FreightPost::query()
                ->active()
                ->withinPickupWindow()
                ->visible()
                ->count();

            $props['recentFreight'] = (clone $owned)
                ->with(['originLocation:id,name', 'destinationLocation:id,name'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get([
                    'id',
                    'ulid',
                    'item_name',
                    'status',
                    'origin_location_id',
                    'destination_location_id',
                    'arrived_at',
                    'created_at',
                ])
                ->map(fn (FreightPost $post) => [
                    'ulid' => $post->ulid,
                    'title' => $post->item_name ?: 'Freight post',
                    'route' => $post->originLocation && $post->destinationLocation
                        ? $post->originLocation->name.' → '.$post->destinationLocation->name
                        : 'Route not set',
                    'status' => $post->arrived_at ? 'arrived' : $post->status,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('dashboard', $props);
    }
}
