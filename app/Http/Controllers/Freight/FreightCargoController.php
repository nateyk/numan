<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFreightCargoRequest;
use App\Models\CargoType;
use App\Models\FreightPost;
use App\Models\TruckType;
use App\Services\FreightCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FreightCargoController extends Controller
{
    public function show(Request $request, string $ulid): Response
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('view', $post);

        return Inertia::render('freight/cargo', [
            'post' => $post,
            'cargoTypes' => CargoType::active()->ordered()->get(['id', 'name', 'slug']),
            'truckTypes' => TruckType::active()->ordered()->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(StoreFreightCargoRequest $request, string $ulid, FreightCompletionService $completion): RedirectResponse
    {
        $post = FreightPost::where('ulid', $ulid)->firstOrFail();
        Gate::authorize('update', $post);

        $post->fill($request->validated());
        $post->save();

        $completion->update($post);

        return redirect()->route('freight.schedule.show', ['ulid' => $post->ulid]);
    }
}
