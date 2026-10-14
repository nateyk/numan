<?php

namespace App\Http\Controllers;

use App\Enums\TransporterStatus;
use App\Http\Requests\StoreTransporterProfileRequest;
use App\Http\Resources\TransporterPublicResource;
use App\Models\CargoType;
use App\Models\Location;
use App\Models\TransporterDocument;
use App\Models\TransporterProfile;
use App\Models\TruckType;
use App\Models\User;
use App\Services\TransporterCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransporterProfileController extends Controller
{
    public function __construct(
        protected TransporterCompletionService $completionService
    ) {}

    /**
     * My transporter profile wizard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->transporterProfile()->with(['truckTypes', 'routes.originLocation', 'routes.destinationLocation', 'documents', 'primaryLocation'])->first();

        return Inertia::render('transporter-profile/index', [
            'profile' => $profile ? $this->formatProfile($profile) : null,
            'truckTypes' => TruckType::active()->ordered()->get(['id', 'name', 'slug', 'icon', 'description']),
            'cargoTypes' => CargoType::active()->ordered()->get(['id', 'name', 'slug']),
            'locations' => Location::active()->orderBy('name')->limit(50)->get(['id', 'name', 'type', 'region_name']),
            'completionScore' => $profile ? $profile->completion_score : 0,
        ]);
    }

    public function store(StoreTransporterProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->transporterProfile()->exists()) {
            return redirect()->route('transporter.profile.index')->withErrors(['display_name' => 'Profile already exists. Use update.']);
        }

        $data = $request->validated();

        $profile = TransporterProfile::create([
            'user_id' => $user->id,
            'slug' => $this->generateSlug($data['display_name']),
            'display_name' => $data['display_name'],
            'legal_name' => $data['legal_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'business_description' => $data['business_description'] ?? null,
            'is_company' => (bool) ($data['is_company'] ?? false),
            'status' => TransporterStatus::Pending->value,
            'phone_visible' => (bool) ($data['phone_visible'] ?? false),
            'whatsapp_visible' => (bool) ($data['whatsapp_visible'] ?? false),
            'email_visible' => (bool) ($data['email_visible'] ?? false),
            'primary_location_id' => $data['primary_location_id'],
        ]);

        $this->syncContactVisibility($user, $data);

        $this->syncRelations($profile, $data, $request);

        $this->completionService->update($profile);

        return redirect()->route('transporter.profile.index')->with('success', 'Profile submitted for review.');
    }

    public function update(StoreTransporterProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->transporterProfile()->firstOrFail();

        if (! in_array($profile->status, [TransporterStatus::Draft->value, TransporterStatus::Pending->value, TransporterStatus::Rejected->value], true)) {
            return back()->withErrors(['display_name' => 'Profile cannot be edited in its current status.']);
        }

        $data = $request->validated();

        $profile->update([
            'display_name' => $data['display_name'],
            'legal_name' => $data['legal_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'business_description' => $data['business_description'] ?? null,
            'is_company' => (bool) ($data['is_company'] ?? false),
            'status' => TransporterStatus::Pending->value,
            'phone_visible' => (bool) ($data['phone_visible'] ?? false),
            'whatsapp_visible' => (bool) ($data['whatsapp_visible'] ?? false),
            'email_visible' => (bool) ($data['email_visible'] ?? false),
            'primary_location_id' => $data['primary_location_id'],
        ]);

        $this->syncContactVisibility($user, $data);

        $this->syncRelations($profile, $data, $request);

        $this->completionService->update($profile);

        return back()->with('success', 'Profile updated and resubmitted.');
    }

    /**
     * Public directory — only approved profiles.
     */
    public function directory(Request $request): Response
    {
        $query = TransporterProfile::query()
            ->with(['primaryLocation', 'truckTypes', 'routes.originLocation', 'routes.destinationLocation'])
            ->approved()
            ->orderByDesc('updated_at');

        if ($request->filled('truck_type')) {
            $query->whereHas('truckTypes', fn ($q) => $q->where('truck_types.slug', $request->input('truck_type')));
        }

        if ($request->filled('location')) {
            $locId = $request->input('location');
            $query->where(function ($q) use ($locId): void {
                $q->where('primary_location_id', $locId)
                    ->orWhereHas('routes', fn ($rq) => $rq->where('origin_location_id', $locId)->orWhere('destination_location_id', $locId));
            });
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q): void {
                $qq->where('display_name', 'like', "%{$q}%")
                    ->orWhere('legal_name', 'like', "%{$q}%");
            });
        }

        $profiles = $query->paginate(12)->withQueryString();

        // Explicit columns only. The raw model carries phone, whatsapp, email
        // and legal_name, which are released solely through the reveal action.
        return Inertia::render('transporters/index', [
            'profiles' => TransporterPublicResource::collection(
                $profiles->getCollection()
            )->resolve(),
            'total' => $profiles->total(),
            'links' => $profiles->links(),
            'truckTypes' => TruckType::active()->ordered()->get(['id', 'name', 'slug']),
            'filters' => [
                'q' => $request->query('q'),
                'truck_type' => $request->query('truck_type'),
                'location' => $request->query('location'),
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $profile = TransporterProfile::query()
            ->with(['primaryLocation', 'truckTypes', 'routes.originLocation', 'routes.destinationLocation', 'documents'])
            ->where('slug', $slug)
            ->approved()
            ->firstOrFail();

        // Explicit columns only. Contact details are released through the reveal
        // action, never on the public profile page.
        return Inertia::render('transporters/show', [
            'profile' => (new TransporterPublicResource($profile))->resolve(),
        ]);
    }

    private function syncRelations(TransporterProfile $profile, array $data, Request $request): void
    {
        if (isset($data['truck_type_ids'])) {
            $profile->truckTypes()->sync($data['truck_type_ids'] ?? []);
        }

        if (isset($data['routes'])) {
            $profile->routes()->delete();
            foreach ($data['routes'] as $route) {
                if (empty($route['origin_location_id']) || empty($route['destination_location_id'])) {
                    continue;
                }
                if ((int) $route['origin_location_id'] === (int) $route['destination_location_id']) {
                    continue;
                }
                $profile->routes()->create([
                    'origin_location_id' => $route['origin_location_id'],
                    'destination_location_id' => $route['destination_location_id'],
                ]);
            }
        }

        // Handle documents upload
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            $types = $request->input('documents_types', []);
            foreach ($files as $idx => $file) {
                $type = $types[$idx] ?? 'other';
                $original = $file->getClientOriginalName();
                $ext = $file->getClientOriginalExtension();
                $storedPath = $file->storeAs(
                    'transporter-documents/'.$profile->id,
                    Str::uuid().'.'.$ext,
                    'private'
                );

                TransporterDocument::create([
                    'transporter_profile_id' => $profile->id,
                    'type' => $type,
                    'original_name' => $original,
                    'stored_path' => $storedPath,
                    'mime' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize(),
                    'status' => 'pending',
                ]);
            }
        }
    }

    private function generateSlug(string $displayName): string
    {
        $base = Str::slug($displayName);
        $slug = $base.'-'.Str::random(4);
        $i = 1;
        while (TransporterProfile::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::random(4).$i++;
        }

        return $slug;
    }

    private function formatProfile(TransporterProfile $profile, bool $respectVisibility = false): array
    {
        $data = $profile->toArray();
        $data['truck_types'] = $profile->truckTypes;
        $data['routes'] = $profile->routes->map(fn ($r) => [
            'id' => $r->id,
            'origin' => $r->originLocation,
            'destination' => $r->destinationLocation,
        ]);
        $data['documents'] = $profile->documents->map(fn ($d) => [
            'id' => $d->id,
            'type' => $d->type,
            'original_name' => $d->original_name,
            'status' => $d->status,
            'created_at' => $d->created_at,
        ]);
        $data['primary_location'] = $profile->primaryLocation;

        if ($respectVisibility) {
            // Read the choice from the profile, which is where ContactRevealService
            // reads it from. Reading it off the transporter row here meant the
            // public page and the reveal could disagree about whether a number was
            // allowed out.
            $identity = $profile->user?->profile;

            if (! (bool) ($identity?->phone_visible ?? true)) {
                $data['phone'] = null;
            }
            if (! (bool) ($identity?->whatsapp_visible ?? true)) {
                $data['whatsapp'] = null;
            }
            if (! (bool) ($identity?->email_visible ?? true)) {
                $data['email'] = null;
            }
        }

        return $data;
    }

    /**
     * Record the contact-visibility choices against the user's profile.
     *
     * These live on profiles because that is the record every kind of user has.
     * While the flag existed only on transporter_profiles, a consignor had no way
     * to decline publication of their number, and ContactRevealService released
     * it regardless.
     *
     * The transporter columns are still written, deliberately. They are now a
     * stale copy kept in step with the profile until the separate migration drops
     * them, so that a rollback of that step cannot silently re-expose somebody who
     * had hidden their number.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncContactVisibility(User $user, array $data): void
    {
        $identity = $user->profile()->first();

        if (! $identity) {
            return;
        }

        $identity->forceFill([
            'phone_visible' => (bool) ($data['phone_visible'] ?? false),
            'whatsapp_visible' => (bool) ($data['whatsapp_visible'] ?? false),
            'email_visible' => (bool) ($data['email_visible'] ?? false),
        ])->save();
    }
}
