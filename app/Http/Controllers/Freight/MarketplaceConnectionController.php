<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConnectionResource;
use App\Models\FreightInterest;
use App\Models\MarketplaceConnection;
use App\Services\ContactRevealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceConnectionController extends Controller
{
    public function __construct(
        protected ContactRevealService $reveals,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MarketplaceConnection::class);

        $connections = MarketplaceConnection::query()
            ->where(function ($q) use ($request) {
                $q->where('consignor_user_id', $request->user()->id)
                    ->orWhere('transporter_user_id', $request->user()->id);
            })
            ->with(['freightPost.originLocation', 'freightPost.destinationLocation', 'consignor.profile', 'transporter.profile', 'outcome'])
            ->latest()
            ->paginate(12);

        return Inertia::render('connections/index', [
            'connections' => ConnectionResource::collection($connections->getCollection())->resolve(),
            'total' => $connections->total(),
            'links' => $connections->links(),
        ]);
    }

    public function show(Request $request, string $public_id): Response
    {
        $connection = MarketplaceConnection::where('public_id', $public_id)
            ->with([
                'freightPost.originLocation',
                'freightPost.destinationLocation',
                'freightPost.cargoType',
                'freightPost.truckType',
                'consignor.profile',
                'transporter.profile',
                'outcome',
            ])
            ->firstOrFail();

        Gate::authorize('view', $connection);

        $viewer = $request->user();
        $counterparty = $viewer->id === $connection->consignor_user_id
            ? $connection->transporter
            : $connection->consignor;

        // Accepting an interest creates the connection but does NOT reveal.
        // Contact data is returned only by the explicit reveal endpoint, never
        // in this page payload. Including it here would make page load an
        // automatic reveal.
        $canReveal = $counterparty !== null && $this->reveals->canReveal($viewer, $counterparty);

        return Inertia::render('connections/show', [
            'connection' => (new ConnectionResource($connection))->resolve(),
            'contactDetails' => null,
            'canReveal' => $canReveal,
            'alreadyRevealed' => false,
            'counterparty' => $counterparty ? [
                'id' => (int) $counterparty->id,
                'display_name' => $counterparty->profile?->display_name ?? $counterparty->name,
                'is_verified' => (bool) $counterparty->profile?->verification_level,
            ] : null,
        ]);
    }

    /**
     * The explicit reveal action.
     *
     * Unmetered in 4a. Requires a live interest or an existing connection
     * between the two users; anyone else gets a 403.
     */
    public function reveal(Request $request, string $public_id): RedirectResponse|JsonResponse
    {
        $connection = MarketplaceConnection::where('public_id', $public_id)->firstOrFail();
        Gate::authorize('view', $connection);

        $viewer = $request->user();
        $counterparty = $viewer->id === $connection->consignor_user_id
            ? $connection->transporter
            : $connection->consignor;

        if (! $counterparty || ! $this->reveals->canReveal($viewer, $counterparty)) {
            abort(403);
        }

        $contactDetails = $this->reveals->contactDetailsFor($viewer, $counterparty);

        if (! $contactDetails) {
            return back()->withErrors(['contact' => 'No contact details are on file for this user.']);
        }

        if ($request->wantsJson()) {
            return response()->json(['contact' => $contactDetails]);
        }

        return back()->with('revealed_contact', $contactDetails);
    }

    /**
     * Reveal from an interest, before any connection exists.
     *
     * The counterparty is resolved relative to the viewer: the interest's owner
     * when the viewer is the consignor, or the freight post's owner when the
     * viewer is the transporter.
     */
    public function revealFromInterest(Request $request, int $interest): RedirectResponse|JsonResponse
    {
        $interest = FreightInterest::with(['freightPost.user.profile', 'user.profile'])->findOrFail($interest);
        Gate::authorize('view', $interest->freightPost);

        $viewer = $request->user();

        $counterparty = $viewer->id === $interest->user_id
            ? $interest->freightPost?->user
            : $interest->user;

        if (! $counterparty || ! $this->reveals->canReveal($viewer, $counterparty)) {
            abort(403);
        }

        $contactDetails = $this->reveals->contactDetailsFor($viewer, $counterparty);

        if (! $contactDetails) {
            return back()->withErrors(['contact' => 'No contact details are on file for this user.']);
        }

        if ($request->wantsJson()) {
            return response()->json(['contact' => $contactDetails]);
        }

        return back()->with('revealed_contact', $contactDetails);
    }

    /**
     * Whether this viewer has already revealed this counterparty.
     *
     * There is no ledger yet, so a reveal cannot be remembered across page
     * loads. "Already revealed, shown again without a new action" arrives with
     * the contact_reveals ledger in 4b. Until then the page always offers the
     * button and never inlines contact data.
     */
    private function wasRevealed(MarketplaceConnection $connection, $user): bool
    {
        return false;
    }
}
