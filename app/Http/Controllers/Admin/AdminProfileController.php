<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProfileStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateProfileListingRequest;
use App\Http\Requests\UpdateProfileVerificationRequest;
use App\Models\Profile;
use App\Models\ProfileDocument;
use App\Services\ProfileDocumentStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The single admin surface for every public profile.
 *
 * Consignors, carriers, and people who do both share one record, so the list,
 * the filters, and the moderation actions all work on that record. The old
 * transporter-only screen could not see a consignor at all.
 */
class AdminProfileController extends Controller
{
    public function __construct(
        protected ProfileDocumentStorage $documents,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('moderate', Profile::class);

        $type = (string) $request->input('type', 'all');
        $listingStatus = (string) $request->input('listing_status', 'all');
        $verificationStatus = (string) $request->input('verification_status', 'all');
        $search = trim((string) $request->input('search', ''));

        $query = Profile::query()
            ->with(['user', 'primaryLocation'])
            ->latest('updated_at');

        $this->applyType($query, $type);

        if (in_array($listingStatus, ProfileStatus::values(), true)) {
            $query->where('status', $listingStatus);
        }

        if (in_array($verificationStatus, VerificationStatus::values(), true)) {
            $query->where('verification_status', $verificationStatus);
        }

        if ($search !== '') {
            $like = "%{$search}%";

            $query->where(function (Builder $profiles) use ($like): void {
                $profiles->where('display_name', 'like', $like)
                    ->orWhere('legal_name', 'like', $like)
                    ->orWhereHas('user', fn (Builder $users) => $users->where('email', 'like', $like));
            });
        }

        $profiles = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Profile $profile): array => $this->summary($profile));

        return Inertia::render('admin/profiles/index', [
            'profiles' => $profiles,
            'counts' => [
                'all' => Profile::query()->count(),
                'consignor' => Profile::query()
                    ->where('posts_freight', true)
                    ->where('provides_transport', false)
                    ->count(),
                'transporter' => Profile::query()
                    ->where('provides_transport', true)
                    ->where('posts_freight', false)
                    ->count(),
                'both' => Profile::query()
                    ->where('posts_freight', true)
                    ->where('provides_transport', true)
                    ->count(),
                'pending_verification' => Profile::query()
                    ->where('verification_status', VerificationStatus::Pending->value)
                    ->count(),
            ],
            'filters' => [
                'type' => $type,
                'listing_status' => $listingStatus,
                'verification_status' => $verificationStatus,
                'search' => $search,
            ],
        ]);
    }

    public function show(Profile $profile): Response
    {
        Gate::authorize('moderate', Profile::class);

        $profile->load([
            'user',
            'primaryLocation',
            'truckTypes',
            'routes.originLocation',
            'routes.destinationLocation',
            'documents.reviewer',
            'moderations.moderator',
        ]);

        return Inertia::render('admin/profiles/show', [
            'profile' => [
                'id' => $profile->id,
                'slug' => $profile->slug,
                'display_name' => $profile->display_name,
                'type' => $this->typeOf($profile),
                'account_type' => $profile->account_type,
                'phone' => $profile->phone,
                'whatsapp' => $profile->whatsapp,
                'email' => $profile->email,
                'business_description' => $profile->business_description,
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
                'verification_requested_at' => $profile->verification_requested_at?->toIso8601String(),
                'verification_reviewed_at' => $profile->verification_reviewed_at?->toIso8601String(),
                'completion_score' => $profile->completion_score,
                'primary_location' => $profile->primaryLocation === null ? null : [
                    'id' => $profile->primaryLocation->id,
                    'name' => $profile->primaryLocation->name,
                    'region_name' => $profile->primaryLocation->region_name,
                ],
                'truck_types' => $profile->truckTypes->map(fn ($type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                ])->values(),
                'routes' => $profile->routes->map(fn ($route): array => [
                    'id' => $route->id,
                    'origin' => $route->originLocation?->name,
                    'destination' => $route->destinationLocation?->name,
                ])->values(),
                'documents' => $profile->documents->map(fn (ProfileDocument $document): array => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'original_name' => $document->original_name,
                    'status' => $document->status,
                    'mime' => $document->mime,
                    'size' => $document->size,
                    'reviewed_at' => $document->reviewed_at?->toIso8601String(),
                    'reviewer' => $document->reviewer?->name,
                    'uploaded_at' => $document->created_at?->toIso8601String(),
                ])->values(),
                'moderations' => $profile->moderations->map(fn ($moderation): array => [
                    'id' => $moderation->id,
                    'kind' => $moderation->kind,
                    'from_status' => $moderation->from_status,
                    'to_status' => $moderation->to_status,
                    'reason' => $moderation->reason,
                    'moderator' => $moderation->moderator?->name,
                    'created_at' => $moderation->created_at?->toIso8601String(),
                ])->values(),
                'user' => [
                    'id' => $profile->user->id,
                    'name' => $profile->user->name,
                    'email' => $profile->user->email,
                ],
                'created_at' => $profile->created_at?->toIso8601String(),
                'updated_at' => $profile->updated_at?->toIso8601String(),
            ],
            'public_url' => "/p/{$profile->slug}",
        ]);
    }

    public function updateListing(ModerateProfileListingRequest $request, Profile $profile): RedirectResponse
    {
        $validated = $request->validated();

        $from = $profile->status;
        $to = $validated['status'];

        $profile->forceFill(['status' => $to])->save();

        $profile->moderations()->create([
            'kind' => 'listing',
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $validated['reason'] ?? null,
            'moderator_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Listing '.$to.'.');
    }

    public function updateVerification(UpdateProfileVerificationRequest $request, Profile $profile): RedirectResponse
    {
        $validated = $request->validated();

        $from = $profile->verification_status;
        $to = $validated['status'];
        $note = $validated['note'] ?? null;

        $profile->forceFill([
            'verification_status' => $to,
            'verification_note' => $note,
            'verification_reviewed_at' => now(),
        ])->save();

        $profile->moderations()->create([
            'kind' => 'verification',
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $note,
            'moderator_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Verification '.$to.'.');
    }

    public function reviewDocument(Request $request, ProfileDocument $document): RedirectResponse
    {
        Gate::authorize('moderate', Profile::class);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $document->update([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Document '.$validated['status'].'.');
    }

    public function downloadDocument(ProfileDocument $document): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('moderate', Profile::class);

        return $this->documents->download($document);
    }

    /**
     * @param  Builder<Profile>  $query
     */
    private function applyType(Builder $query, string $type): void
    {
        match ($type) {
            'consignor' => $query->where('posts_freight', true)->where('provides_transport', false),
            'transporter' => $query->where('provides_transport', true)->where('posts_freight', false),
            'both' => $query->where('posts_freight', true)->where('provides_transport', true),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Profile $profile): array
    {
        return [
            'id' => $profile->id,
            'slug' => $profile->slug,
            'display_name' => $profile->display_name,
            'legal_name' => $profile->legal_name,
            'type' => $this->typeOf($profile),
            'account_type' => $profile->account_type,
            'email' => $profile->user?->email,
            'user_name' => $profile->user?->name,
            'posts_freight' => (bool) $profile->posts_freight,
            'provides_transport' => (bool) $profile->provides_transport,
            'status' => $profile->status,
            'verification_status' => $profile->verification_status,
            'primary_location' => $profile->primaryLocation?->name,
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }

    private function typeOf(Profile $profile): string
    {
        return match (true) {
            $profile->posts_freight && $profile->provides_transport => 'both',
            $profile->provides_transport => 'transporter',
            default => 'consignor',
        };
    }
}
