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

        $posts = FreightPost::query()
            ->ownedBy($request->user())
            ->with(['originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('freight/index', [
            'posts' => $posts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', FreightPost::class);

        $post = FreightPost::create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $request->user()->id,
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
