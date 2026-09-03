<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FreightPostStatus;
use App\Http\Controllers\Controller;
use App\Models\FreightPost;
use App\Notifications\FreightPostModerated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminFreightController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('moderate', FreightPost::class);

        $status = $request->query('status', FreightPostStatus::Submitted->value);
        $search = $request->query('search');

        $query = FreightPost::query()
            ->with(['user', 'originLocation', 'destinationLocation', 'cargoType', 'truckType'])
            ->latest();

        if ($status !== 'all' && in_array($status, FreightPostStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('cargo_description', 'like', "%{$search}%")
                    ->orWhere('ulid', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('originLocation', fn ($oq) => $oq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinationLocation', fn ($dq) => $dq->where('name', 'like', "%{$search}%"));
            });
        }

        $posts = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => FreightPost::count(),
            'draft' => FreightPost::where('status', FreightPostStatus::Draft->value)->count(),
            'submitted' => FreightPost::where('status', FreightPostStatus::Submitted->value)->count(),
            'under_review' => FreightPost::where('status', FreightPostStatus::UnderReview->value)->count(),
            'active' => FreightPost::where('status', FreightPostStatus::Active->value)->count(),
            'closed' => FreightPost::where('status', FreightPostStatus::Closed->value)->count(),
            'expired' => FreightPost::where('status', FreightPostStatus::Expired->value)->count(),
            'rejected' => FreightPost::where('status', FreightPostStatus::Rejected->value)->count(),
        ];

        return Inertia::render('admin/freight/index', [
            'posts' => $posts,
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function show(string $ulid): Response
    {
        Gate::authorize('moderate', FreightPost::class);

        $post = FreightPost::where('ulid', $ulid)
            ->with(['user.profile', 'originLocation', 'destinationLocation', 'cargoType', 'truckType', 'routeSnapshot', 'moderationLogs.moderator'])
            ->firstOrFail();

        return Inertia::render('admin/freight/show', [
            'post' => [
                'ulid' => $post->ulid,
                'status' => $post->status,
                'cargo_description' => $post->cargo_description,
                'cargo_type' => $post->cargoType,
                'truck_type' => $post->truckType,
                'truck_count' => $post->truck_count,
                'schedule_type' => $post->schedule_type,
                'pickup_date' => $post->pickup_date,
                'pickup_time' => $post->pickup_time,
                'origin_exact_lat' => $post->origin_exact_lat,
                'origin_exact_lng' => $post->origin_exact_lng,
                'destination_exact_lat' => $post->destination_exact_lat,
                'destination_exact_lng' => $post->destination_exact_lng,
                'origin_location' => $post->originLocation,
                'destination_location' => $post->destinationLocation,
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'email' => $post->user->email,
                ] : null,
                'route_snapshot' => $post->routeSnapshot,
                'moderation_logs' => $post->moderationLogs->map(fn ($m) => [
                    'id' => $m->id,
                    'from_status' => $m->from_status,
                    'to_status' => $m->to_status,
                    'reason' => $m->reason,
                    'moderator' => $m->moderator?->name,
                    'created_at' => $m->created_at?->toISOString(),
                ]),
                'created_at' => $post->created_at?->toISOString(),
            ],
        ]);
    }

    public function moderate(Request $request, string $ulid): RedirectResponse
    {
        Gate::authorize('moderate', FreightPost::class);

        $post = FreightPost::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'to_status' => ['required', Rule::in(FreightPostStatus::values())],
            'reason' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('to_status') === FreightPostStatus::Rejected->value)],
        ]);

        $from = FreightPostStatus::tryFrom($post->status);
        $to = FreightPostStatus::tryFrom($validated['to_status']);

        if (! $from || ! $to || ! $from->canTransitionTo($to)) {
            return back()->withErrors(['to_status' => 'Invalid status transition.']);
        }

        DB::transaction(function () use ($post, $from, $to, $validated, $request): void {
            $post->update(['status' => $to->value]);

            $post->moderationLogs()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $validated['reason'] ?? null,
                'moderator_id' => $request->user()->id,
            ]);
        });

        $action = match ($to->value) {
            FreightPostStatus::Active->value => 'approved',
            FreightPostStatus::Rejected->value => 'rejected',
            default => 'updated',
        };
        Notification::send($post->user, new FreightPostModerated($post->fresh(), $action));

        return back()->with('success', 'Freight post moderated to '.$to->value.'.');
    }
}
