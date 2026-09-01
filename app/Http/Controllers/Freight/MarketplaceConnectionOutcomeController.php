<?php

namespace App\Http\Controllers\Freight;

use App\Enums\ConnectionOutcome;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceConnectionOutcome;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MarketplaceConnectionOutcomeController extends Controller
{
    public function store(Request $request, string $public_id): RedirectResponse
    {
        $connection = MarketplaceConnection::where('public_id', $public_id)->firstOrFail();
        Gate::authorize('update', $connection);

        $validated = $request->validate([
            'outcome' => ['required', 'string', \Illuminate\Validation\Rule::in(ConnectionOutcome::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $outcome = $connection->outcome()->updateOrCreate(
            ['marketplace_connection_id' => $connection->id],
            [
                'outcome' => $validated['outcome'],
                'notes' => $validated['notes'] ?? null,
                'reported_by' => $request->user()->id,
            ]
        );

        $connection->auditLogs()->create([
            'action' => 'outcome_recorded',
            'actor_id' => $request->user()->id,
            'metadata' => ['outcome' => $validated['outcome']],
        ]);

        return back()->with('success', 'Outcome recorded.');
    }
}