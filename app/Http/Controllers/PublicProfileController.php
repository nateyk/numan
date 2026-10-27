<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\LegalForm;
use App\Enums\ProfileStatus;
use App\Http\Requests\UpdatePublicProfileRequest;
use App\Http\Requests\UpdateTransportProfileRequest;
use App\Http\Resources\PublicProfileResource;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TruckType;
use App\Services\PhoneNumberService;
use App\Services\ProfileCompletionService;
use App\Services\SavePublicProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single management surface for a user's public marketplace profile.
 *
 * Both capabilities live on one record, so one page edits both. The carrier
 * section is part of this page rather than a separate form.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        protected SavePublicProfile $profiles,
        protected ProfileCompletionService $completion,
    ) {}

    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // A superadmin manages other people's profiles through the admin list.
        // Sending them there also keeps this page's owner-only policy honest.
        if ($user->is_superadmin) {
            return redirect('/admin/profiles');
        }

        $profile = $this->profiles->save($user, []);
        Gate::authorize('update', $profile);

        $listingRejectionReason = null;

        if ($profile->status === ProfileStatus::Rejected->value) {
            $listingRejectionReason = $profile->moderations()
                ->where('kind', 'listing')
                ->where('to_status', ProfileStatus::Rejected->value)
                ->latest()
                ->value('reason');
        }

        return Inertia::render('public-profile/index', [
            'profile' => $this->profilePayload($profile),
            'locations' => Location::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'region_name']),
            'truckTypes' => TruckType::query()
                ->active()
                ->ordered()
                ->get(['id', 'name']),
            'accountTypes' => AccountType::cases(),
            'legalForms' => LegalForm::cases(),
            'completionScore' => $this->completion->calculate($profile),
            'listing_rejection_reason' => $listingRejectionReason,
        ]);
    }

    /**
     * The public carrier directory.
     *
     * Only approved transport profiles, and only fields the public resource
     * allows. The query uses withCount/withExists so a card can say whether
     * documents exist and were reviewed without loading a single document row.
     */
    public function directory(Request $request): Response
    {
        $query = Profile::query()
            ->where('provides_transport', true)
            ->where('status', ProfileStatus::Approved->value)
            ->with([
                'user',
                'primaryLocation',
                'truckTypes',
                'routes.originLocation',
                'routes.destinationLocation',
            ])
            ->withCount('documents')
            ->withExists([
                'documents as has_approved_documents' => fn ($documents) => $documents->where('status', 'approved'),
            ])
            ->orderByDesc('updated_at');

        if ($request->filled('truck_type')) {
            $truckType = (string) $request->input('truck_type');

            $query->whereHas('truckTypes', fn ($types) => $types->where('truck_types.slug', $truckType));
        }

        if ($request->filled('location')) {
            $location = trim((string) $request->input('location'));

            $query->where(function ($profiles) use ($location): void {
                if (ctype_digit($location)) {
                    $id = (int) $location;

                    $profiles->where('primary_location_id', $id)
                        ->orWhereHas('routes', fn ($routes) => $routes
                            ->where('origin_location_id', $id)
                            ->orWhere('destination_location_id', $id));

                    return;
                }

                $like = "%{$location}%";

                $profiles
                    ->whereHas('primaryLocation', fn ($primary) => $primary->where('name', 'like', $like))
                    ->orWhereHas('routes.originLocation', fn ($origin) => $origin->where('name', 'like', $like))
                    ->orWhereHas('routes.destinationLocation', fn ($destination) => $destination->where('name', 'like', $like));
            });
        }

        if ($request->filled('q')) {
            $search = (string) $request->input('q');

            $query->where(function ($profiles) use ($search): void {
                $profiles->where('display_name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%");
            });
        }

        $profiles = $query->paginate(12)->withQueryString();

        return Inertia::render('transporters/index', [
            'profiles' => PublicProfileResource::collection($profiles),
            'total' => $profiles->total(),
            'links' => $profiles->linkCollection()->toArray(),
            'truckTypes' => TruckType::query()
                ->active()
                ->ordered()
                ->get(['id', 'name', 'slug']),
            'filters' => [
                'q' => $request->query('q'),
                'truck_type' => $request->query('truck_type'),
                'location' => $request->query('location'),
            ],
        ]);
    }

    public function update(UpdatePublicProfileRequest $request, PhoneNumberService $phones): RedirectResponse
    {
        $user = $request->user();
        $profile = $this->profiles->save($user, []);
        Gate::authorize('update', $profile);

        $data = $request->validated();

        if (array_key_exists('phone', $data)) {
            if (filled($data['phone'])) {
                $normalized = $phones->normalize($data['phone']);

                if ($normalized === null) {
                    return back()
                        ->withErrors(['phone' => PhoneNumberService::INVALID_MESSAGE])
                        ->withInput();
                }

                $data['phone'] = $normalized;
            } else {
                $data['phone'] = null;
            }
        }

        $profile = $this->profiles->save($user, $data);
        $this->completion->update($profile);

        return back()->with('success', 'Profile saved.');
    }

    public function updateTransport(UpdateTransportProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $this->profiles->save($user, []);
        Gate::authorize('update', $profile);

        // Refuse before touching child rows, so a profile that does not carry
        // freight cannot accumulate carrier data it will never show.
        if (! $profile->provides_transport) {
            return back()
                ->withErrors(['transport' => 'Turn on "I provide transport" before adding carrier details.'])
                ->withInput();
        }

        $data = $request->validated();

        // Both keys are optional and a partial save must not erase the other
        // section.
        if (array_key_exists('truck_type_ids', $data)) {
            $profile->truckTypes()->sync($data['truck_type_ids'] ?? []);
        }

        if (array_key_exists('routes', $data)) {
            $profile->routes()->delete();

            foreach ($data['routes'] ?? [] as $route) {
                $profile->routes()->create([
                    'origin_location_id' => $route['origin_location_id'],
                    'destination_location_id' => $route['destination_location_id'],
                ]);
            }
        }

        // Approved listings keep their approval across an edit; everything else
        // goes back into the review queue.
        if ($profile->status !== ProfileStatus::Approved->value) {
            $profile->forceFill(['status' => ProfileStatus::Pending->value])->save();
        }

        $this->completion->update($profile->refresh());

        return back()->with('success', 'Carrier details saved and submitted for review.');
    }

    /**
     * The management page's own view of the profile.
     *
     * Explicit rather than the model, so the contact fields the owner may edit
     * are the only ones the page receives and relations cannot leak an
     * unintended attribute.
     *
     * @return array<string, mixed>
     */
    private function profilePayload(Profile $profile): array
    {
        return [
            'id' => $profile->id,
            'slug' => $profile->slug,
            'display_name' => $profile->display_name,
            'account_type' => $profile->account_type,
            'phone' => $profile->phone,
            'whatsapp' => $profile->whatsapp,
            'email' => $profile->email,
            'business_description' => $profile->business_description,
            'primary_location_id' => $profile->primary_location_id,
            'legal_name' => $profile->legal_name,
            'legal_form' => $profile->legal_form,
            'legal_form_other' => $profile->legal_form_other,
            'posts_freight' => (bool) $profile->posts_freight,
            'provides_transport' => (bool) $profile->provides_transport,
            'phone_visible' => (bool) $profile->phone_visible,
            'whatsapp_visible' => (bool) $profile->whatsapp_visible,
            'email_visible' => (bool) $profile->email_visible,
            'status' => $profile->status,
            'verification_status' => $profile->verification_status,
            'verification_note' => $profile->verification_note,
            'completion_score' => $profile->completion_score,
            'truck_type_ids' => $profile->truckTypes()->pluck('truck_types.id')->all(),
            'routes' => $profile->routes()
                ->get(['id', 'origin_location_id', 'destination_location_id'])
                ->map(fn ($route): array => [
                    'id' => $route->id,
                    'origin_location_id' => $route->origin_location_id,
                    'destination_location_id' => $route->destination_location_id,
                ])
                ->all(),
        ];
    }
}
