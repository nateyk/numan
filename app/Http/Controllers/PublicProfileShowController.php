<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicProfileResource;
use App\Models\ContactReveal;
use App\Models\FreightPost;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Services\ContactDetailsResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One public profile, at /p/{slug}.
 *
 * The page is public only when the profile is an approved carrier. Everything
 * else is visible to its owner, a superadmin, or a viewer already in the reveal
 * ledger — and to nobody else, not even as a 403. A denied view is a 404 so the
 * page cannot be used to confirm that a hidden profile exists.
 */
class PublicProfileShowController extends Controller
{
    public function __construct(
        protected ContactDetailsResolver $contacts,
    ) {}

    public function show(Request $request, Profile $profile): Response
    {
        // allows(), not authorize(): a 403 would confirm the profile exists.
        if (! Gate::allows('view', $profile)) {
            abort(404);
        }

        $profile->load([
            'user',
            'primaryLocation',
            'truckTypes',
            'routes.originLocation',
            'routes.destinationLocation',
        ])->loadCount('documents')->loadExists([
            'documents as has_approved_documents' => fn ($query) => $query->where('status', 'approved'),
        ]);

        $viewer = $request->user();
        $isOwner = $viewer !== null && (int) $viewer->id === (int) $profile->user_id;

        $reveal = $viewer === null
            ? null
            : ContactReveal::query()
                ->where('revealer_id', $viewer->id)
                ->where('revealed_id', $profile->user_id)
                ->latest()
                ->first();

        $payload = [
            'profile' => (new PublicProfileResource($profile))->resolve(),
            'is_owner' => $isOwner,
            'already_revealed' => $reveal !== null,
            // A search engine may index only the public directory entry. A
            // consignor's page, a pending carrier, and the owner's own preview
            // all stay out of the index.
            'noindex' => ! ($profile->provides_transport && $profile->isApproved()),
        ];

        if ($reveal !== null) {
            $payload['reveal_context_url'] = $this->revealContextUrl($reveal);
        }

        // The owner sees exactly what a reveal would release, so a support
        // question about a hidden number can be answered from this page. A
        // superadmin sees the same during a moderation review. Nobody else
        // receives the key at all.
        if ($isOwner || (bool) $viewer?->is_superadmin) {
            $payload['contact_preview'] = $this->contacts->resolve($profile->user);
        }

        return Inertia::render('profiles/show', $payload);
    }

    /**
     * Where this viewer first met the owner.
     *
     * A reveal is free to repeat, so the page links back to the freight post or
     * connection the ledger row recorded. Older rows may carry neither.
     */
    private function revealContextUrl(ContactReveal $reveal): string
    {
        if ($reveal->freight_post_id !== null) {
            $ulid = FreightPost::query()->whereKey($reveal->freight_post_id)->value('ulid');

            if ($ulid !== null) {
                return route('freight.show', ['ulid' => $ulid]);
            }
        }

        if ($reveal->connection_id !== null) {
            $publicId = MarketplaceConnection::query()->whereKey($reveal->connection_id)->value('public_id');

            if ($publicId !== null) {
                return route('connections.show', ['public_id' => $publicId]);
            }
        }

        return route('freight.discovery');
    }
}
