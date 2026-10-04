<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransporterStatus;
use App\Enums\VerificationLevel;
use App\Http\Controllers\Controller;
use App\Models\TransporterDocument;
use App\Models\TransporterProfile;
use App\Models\TransporterProfileModeration;
use App\Services\TransporterDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminTransporterController extends Controller
{
    public function __construct(
        protected TransporterDocumentStorage $documents,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $search = $request->input('search');

        $query = TransporterProfile::query()
            ->with(['user', 'primaryLocation'])
            ->latest();

        if ($status !== 'all' && in_array($status, TransporterStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%"));
            });
        }

        $profiles = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => TransporterProfile::count(),
            'pending' => TransporterProfile::where('status', TransporterStatus::Pending->value)->count(),
            'approved' => TransporterProfile::where('status', TransporterStatus::Approved->value)->count(),
            'rejected' => TransporterProfile::where('status', TransporterStatus::Rejected->value)->count(),
        ];

        return Inertia::render('admin/transporters/index', [
            'profiles' => $profiles,
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function show(TransporterProfile $transporterProfile): Response
    {
        $transporterProfile->load(['user', 'primaryLocation', 'truckTypes', 'routes.originLocation', 'routes.destinationLocation', 'documents.reviewer', 'moderations.moderator']);

        return Inertia::render('admin/transporters/show', [
            'profile' => [
                'id' => $transporterProfile->id,
                'slug' => $transporterProfile->slug,
                'display_name' => $transporterProfile->display_name,
                'legal_name' => $transporterProfile->legal_name,
                'phone' => $transporterProfile->phone,
                'whatsapp' => $transporterProfile->whatsapp,
                'email' => $transporterProfile->email,
                'business_description' => $transporterProfile->business_description,
                'is_company' => $transporterProfile->is_company,
                'status' => $transporterProfile->status,
                'verification_level' => $transporterProfile->verification_level,
                'completion_score' => $transporterProfile->completion_score,
                'phone_visible' => $transporterProfile->phone_visible,
                'whatsapp_visible' => $transporterProfile->whatsapp_visible,
                'email_visible' => $transporterProfile->email_visible,
                'primary_location' => $transporterProfile->primaryLocation,
                'truck_types' => $transporterProfile->truckTypes,
                'routes' => $transporterProfile->routes->map(fn ($r) => [
                    'id' => $r->id,
                    'origin' => $r->originLocation,
                    'destination' => $r->destinationLocation,
                ]),
                'documents' => $transporterProfile->documents,
                'moderations' => $transporterProfile->moderations->map(fn ($m) => [
                    'id' => $m->id,
                    'from_status' => $m->from_status,
                    'to_status' => $m->to_status,
                    'reason' => $m->reason,
                    'moderator' => $m->moderator?->name,
                    'created_at' => $m->created_at?->toISOString(),
                ]),
                'user' => [
                    'id' => $transporterProfile->user->id,
                    'name' => $transporterProfile->user->name,
                    'email' => $transporterProfile->user->email,
                ],
                'created_at' => $transporterProfile->created_at?->toISOString(),
            ],
        ]);
    }

    public function moderate(Request $request, TransporterProfile $transporterProfile): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([TransporterStatus::Approved->value, TransporterStatus::Rejected->value])],
            'verification_level' => ['nullable', Rule::in(VerificationLevel::values())],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        $from = $transporterProfile->status;
        $to = $validated['status'];

        // Validate transition
        $fromEnum = TransporterStatus::tryFrom($from);
        $toEnum = TransporterStatus::tryFrom($to);
        if ($fromEnum && $toEnum && ! $fromEnum->canTransitionTo($toEnum)) {
            return back()->withErrors(['status' => 'Invalid status transition.']);
        }

        if ($to === TransporterStatus::Rejected->value && empty($validated['reason'])) {
            return back()->withErrors(['reason' => 'Reason is required when rejecting.']);
        }

        $transporterProfile->update([
            'status' => $to,
            'verification_level' => $validated['verification_level'] ?? $transporterProfile->verification_level,
        ]);

        TransporterProfileModeration::create([
            'transporter_profile_id' => $transporterProfile->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $validated['reason'] ?? null,
            'moderator_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Profile '.$to.' successfully.');
    }

    public function reviewDocument(Request $request, TransporterDocument $document): RedirectResponse
    {
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

    public function downloadDocument(Request $request, TransporterDocument $document): BinaryFileResponse|RedirectResponse
    {
        $profile = $document->profile;

        abort_unless($profile !== null, 404);

        Gate::forUser($request->user())->authorize('downloadDocument', $profile);

        // Same disk, same path handling as the upload, via one service. These
        // two used to disagree: uploads wrote to disk('private') and downloads
        // read from disk('local'), and 'private' was not a configured disk.
        return $this->documents->download($document);
    }
}
