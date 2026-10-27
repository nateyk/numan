<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConnectionResource;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplaceConnection;
use App\Models\User;
use App\Services\ContactRevealService;
use Illuminate\Database\Eloquent\Model;
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

        [$counterparty, $canReveal, $revealedContacts] = $this->counterpartyState($request->user(), $connection);

        return Inertia::render('connections/show', [
            'connection' => (new ConnectionResource($connection))->resolve(),
            // Present only once already revealed. Reading it never records a
            // reveal, so loading this page cannot consume quota.
            'revealedContacts' => $revealedContacts,
            'canReveal' => $canReveal,
            'alreadyRevealed' => $revealedContacts !== null,
            // Explicit columns only. The raw User model would carry its email
            // address and its profile's phone number straight into the page.
            'counterparty' => $counterparty ? [
                'id' => (int) $counterparty->id,
                'display_name' => $counterparty->profile?->display_name ?? $counterparty->name,
                'is_verified' => $counterparty->profile?->verification_status === 'verified',
            ] : null,
        ]);
    }

    /**
     * The explicit, metered reveal action.
     */
    public function reveal(Request $request, string $public_id): RedirectResponse|JsonResponse
    {
        $connection = MarketplaceConnection::where('public_id', $public_id)->firstOrFail();
        Gate::authorize('view', $connection);

        $viewer = $request->user();
        $counterparty = $viewer->id === $connection->consignor_user_id
            ? $connection->transporter
            : $connection->consignor;

        if (! $counterparty) {
            abort(403);
        }

        if ($missing = $this->missingPhoneStep($request)) {
            return $missing;
        }

        return $this->performReveal($request, $viewer, $counterparty, $connection);
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

        if (! $counterparty) {
            abort(403);
        }

        // Authorise before offering the phone step. A stranger is not a party to
        // this interest, and prompting them to add a number implies they are one
        // step away from a reveal they will never be allowed to make.
        if (! $this->reveals->canReveal($viewer, $counterparty, $interest)) {
            abort(403);
        }

        if ($missing = $this->missingPhoneStep($request)) {
            return $missing;
        }

        return $this->performReveal($request, $viewer, $counterparty, $interest);
    }

    /**
     * Reveal the consignor's contact directly from a listing.
     *
     * The counterparty is the listing's owner. The hidden-post rule mirrors the
     * public detail page: a lapsed-plan listing 404s for everyone except its
     * owner and a superadmin. An owner POSTing their own reveal is refused
     * after that check — the UI never offers it, and serving their own contact
     * through a metered endpoint would make a nonsense ledger row.
     */
    public function revealFromPost(Request $request, string $ulid): RedirectResponse|JsonResponse
    {
        $post = FreightPost::with('user.profile')->where('ulid', $ulid)->firstOrFail();

        $viewer = $request->user();

        if ($post->isHidden() && ! ($viewer?->is_superadmin || $viewer?->id === $post->user_id)) {
            abort(404);
        }

        Gate::authorize('view', $post);

        $counterparty = $post->user;

        if (! $counterparty || $viewer->id === $counterparty->id) {
            abort(403);
        }

        if ($missing = $this->missingPhoneStep($request)) {
            return $missing;
        }

        return $this->performReveal($request, $viewer, $counterparty, $post);
    }

    /**
     * Run the metered reveal and shape the response.
     *
     * A blocked result carries no contact keys at all — not a null phone, not
     * an empty object — plus the numbers and somewhere to go next.
     */
    private function performReveal(
        Request $request,
        User $viewer,
        User $counterparty,
        ?Model $context,
    ): RedirectResponse|JsonResponse {
        $outcome = $this->reveals->reveal($viewer, $counterparty, $context);

        if ($outcome['status'] === 'blocked') {
            if ($request->wantsJson()) {
                return response()->json([
                    'blocked' => true,
                    'message' => "You have used all {$outcome['limit']} free contact reveals.",
                    'limit' => $outcome['limit'],
                    'used' => $outcome['used'],
                    'subscribe_url' => route('billing.plans'),
                ], 402);
            }

            return back()
                ->with('reveal_blocked', [
                    'limit' => $outcome['limit'],
                    'used' => $outcome['used'],
                ])
                ->withErrors(['contact' => 'You have used all your free contact reveals.']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'contact' => $outcome['contact'],
                'status' => $outcome['status'],
                'remaining' => $outcome['remaining'],
                'limit' => $outcome['limit'],
            ]);
        }

        return back()->with('revealed_contact', $outcome['contact']);
    }

    /**
     * The counterparty, whether a reveal is possible, and the contact if it has
     * already been revealed.
     *
     * @return array{0: ?User, 1: bool, 2: ?array<string, string|null>}
     */
    private function counterpartyState(User $viewer, MarketplaceConnection $connection): array
    {
        $counterparty = $viewer->id === $connection->consignor_user_id
            ? $connection->transporter
            : $connection->consignor;

        if (! $counterparty) {
            return [null, false, null];
        }

        $canReveal = $this->reveals->canReveal($viewer, $counterparty);
        $contact = $canReveal
            ? $this->reveals->contactIfAlreadyRevealed($viewer, $counterparty, $connection)
            : null;

        return [$counterparty, $canReveal, $contact];
    }

    /**
     * A reveal hands over a phone number, so the viewer needs one too —
     * otherwise a revealed counterparty could never call them back.
     *
     * Not a hard block. The shared layout renders one inline field and the user
     * presses Reveal again.
     */
    private function missingPhoneStep(Request $request): RedirectResponse|JsonResponse|null
    {
        if ($request->user()->hasPhoneNumber()) {
            return null;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'requires_phone' => true,
                'message' => 'Add your phone number to continue.',
            ], 422);
        }

        return back()
            ->with('requires_phone', true)
            ->with('requires_phone_reason', 'revealing a contact')
            ->withErrors(['contact' => 'Add your phone number to continue.']);
    }
}
