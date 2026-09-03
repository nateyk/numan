<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightScheduleRequest;
use App\Models\FreightPost;
use App\Services\FreightCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FreightScheduleController extends Controller
{
    public function show(Request $request, string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('view', $post);

        return Inertia::render('freight/schedule', [
            'post' => $post,
        ]);
    }

    public function update(StoreFreightScheduleRequest $request, string $ulid, FreightCompletionService $completion): RedirectResponse
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('update', $post);

        $post->fill($request->validated());
        $post->save();

        $completion->update($post);

        return redirect()->route('freight.review.show', ['ulid' => $post->ulid]);
    }
}
