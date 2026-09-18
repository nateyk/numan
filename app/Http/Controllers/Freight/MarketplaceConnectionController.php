<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceConnectionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MarketplaceConnection::class);

        $connections = MarketplaceConnection::query()
            ->where(function ($q) use ($request) {
                $q->where('consignor_user_id', $request->user()->id)
                    ->orWhere('transporter_user_id', $request->user()->id);
            })
            ->with(['freightPost', 'freightPost.originLocation', 'freightPost.destinationLocation', 'consignor', 'transporter', 'outcome'])
            ->latest()
            ->paginate(12);

        return Inertia::render('connections/index', [
            'connections' => $connections,
        ]);
    }

    public function show(Request $request, string $public_id): Response
    {
        $connection = MarketplaceConnection::where('public_id', $public_id)
            ->with(['freightPost', 'freightPost.originLocation', 'freightPost.destinationLocation', 'freightPost.cargoType', 'freightPost.truckType', 'consignor', 'transporter', 'outcome', 'auditLogs.actor'])
            ->firstOrFail();

        Gate::authorize('view', $connection);

        $contactDetails = $connection->contactDetailsFor($request->user());

        return Inertia::render('connections/show', [
            'connection' => $connection,
            'contactDetails' => $contactDetails,
        ]);
    }

    public function reveal(Request $request, string $public_id): RedirectResponse
    {
        $connection = MarketplaceConnection::where('public_id', $public_id)->firstOrFail();
        Gate::authorize('view', $connection);

        $contactDetails = $connection->contactDetailsFor($request->user());

        if (! $contactDetails) {
            return back()->withErrors(['contact' => 'Not authorized to view contact details.']);
        }

        return back()->with('revealed_contact', $contactDetails);
    }
}
