<?php

namespace App\Http\Controllers\Freight;

use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FreightPostController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FreightPost::class);

        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $query = FreightPost::query()
            ->ownedBy($request->user())
            ->with(['originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot'])
            ->withCount('interests')
            ->latest();

        if ($status !== 'all' && in_array($status, FreightPostStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('cargo_description', 'like', "%{$search}%")
                    ->orWhere('ulid', 'like', "%{$search}%")
                    ->orWhereHas('originLocation', fn ($oq) => $oq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinationLocation', fn ($dq) => $dq->where('name', 'like', "%{$search}%"));
            });
        }

        $posts = $query->paginate(12)->withQueryString();

        $base = FreightPost::ownedBy($request->user());
        $counts = [
            'all' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', FreightPostStatus::Draft->value)->count(),
            'submitted' => (clone $base)->where('status', FreightPostStatus::Submitted->value)->count(),
            'under_review' => (clone $base)->where('status', FreightPostStatus::UnderReview->value)->count(),
            'active' => (clone $base)->where('status', FreightPostStatus::Active->value)->count(),
            'closed' => (clone $base)->where('status', FreightPostStatus::Closed->value)->count(),
            'expired' => (clone $base)->where('status', FreightPostStatus::Expired->value)->count(),
            'rejected' => (clone $base)->where('status', FreightPostStatus::Rejected->value)->count(),
        ];

        return Inertia::render('freight/index', [
            'posts' => $posts,
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', FreightPost::class);

        $workspace = $request->user()->currentWorkspace;

        $post = FreightPost::create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $request->user()->id,
            'workspace_id' => $workspace?->id,
            'status' => FreightPostStatus::Draft->value,
            'completion_score' => 0,
        ]);

        return redirect()->route('freight.route.show', ['ulid' => $post->ulid]);
    }

    public function show(Request $request, string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('view', $post);

        $post->load(['originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot']);

        return Inertia::render('freight/show', [
            'post' => $post,
        ]);
    }
}
