<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
use App\Models\SeoMetadata;
use App\Services\FeatureService;
use App\Services\MarketplaceReadiness;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;
use Laravel\Pennant\Feature;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * Manual payments awaiting a decision.
     */
    private function pendingManualPayments(Request $request): int
    {
        if (! $request->user()?->is_superadmin) {
            return 0;
        }

        if (! Schema::hasTable('marketplace_payment_transactions')) {
            return 0;
        }

        return DB::table('marketplace_payment_transactions')
            ->where('provider', 'manual')
            ->where('status', 'pending_confirmation')
            ->count();
    }

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $currentWorkspace = $user?->currentWorkspace;
        $workspaces = [];

        $showExperienceSurvey = (bool) $user?->qualifiesForExperienceSurvey();
        // Locale is now handled by SetLocale middleware
        $locale = app()->getLocale();

        if ($user) {
            $workspaces = $user->workspaces()
                ->select('workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.logo', 'workspaces.personal_workspace')
                ->get()
                ->map(fn ($workspace) => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'logo' => $workspace->logo,
                    'logo_url' => $workspace->logo_url,
                    'personal_workspace' => $workspace->personal_workspace,
                    'role' => $workspace->pivot->role,
                    'plan' => $workspace->plan_name,
                    'is_current' => $currentWorkspace && $workspace->id === $currentWorkspace->id,
                ]);
        }

        return [
            ...parent::share($request),
            'name' => 'Numan Freight',
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'is_superadmin' => $user->is_superadmin,
                    'locale' => $user->locale,
                    'avatar_url' => $user->avatar_url,
                    'bio' => $user->bio,
                    'timezone' => $user->timezone ?? 'UTC',
                    'date_format' => $user->date_format ?? 'Y-m-d',
                    'notification_preferences' => $user->normalizedNotificationPreferences(),
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                    'tour_completed_at' => $user->tour_completed_at,
                    'created_at' => $user->created_at,
                    'profile_completeness' => $user->profileCompletenessScore(),
                    'phone' => $user->profile?->phone,
                    // The compact public identity the public chrome needs. It
                    // deliberately carries no contact fields: those are released
                    // only through the reveal ledger.
                    'public_profile' => $user->profile ? [
                        'slug' => $user->profile->slug,
                        'display_name' => $user->profile->display_name,
                        'account_type' => $user->profile->account_type,
                        'status' => $user->profile->status,
                        'verification_status' => $user->profile->verification_status,
                        'posts_freight' => (bool) $user->profile->posts_freight,
                        'provides_transport' => (bool) $user->profile->provides_transport,
                    ] : null,
                ] : null,
                'is_impersonating' => $request->session()->has('impersonated_by'),
            ],
            // A one-person personal workspace is an individual account. The
            // chrome uses this to hide team-shaped navigation; the workspace
            // itself still exists underneath.
            'is_individual' => $user?->isIndividual() ?? false,
            'show_experience_survey' => $showExperienceSurvey,
            'locale' => $locale,
            'currentWorkspace' => $currentWorkspace ? [
                'id' => $currentWorkspace->id,
                'name' => $currentWorkspace->name,
                'slug' => $currentWorkspace->slug,
                'logo' => $currentWorkspace->logo,
                'logo_url' => $currentWorkspace->logo_url,
                'personal_workspace' => $currentWorkspace->personal_workspace,
                'owner_id' => $currentWorkspace->owner_id,
                'plan' => $currentWorkspace->plan_name,
                'role' => $currentWorkspace->getUserRole($user),
                'feature_flags' => Feature::for($currentWorkspace)->all(),
            ] : null,
            'workspaces' => $workspaces,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'token' => $request->session()->get('token'),
            ],
            // Set when an action bounced the user back for a missing phone
            // number. The shared layout turns this into a single inline field
            // rather than blocking them.
            'requires_phone' => (bool) $request->session()->get('requires_phone', false),
            'requires_phone_reason' => $request->session()->get('requires_phone_reason'),

            // Set when a contact reveal was refused for quota. Carries only the
            // numbers needed to explain it -- never any contact data, because
            // there is none to give.
            'reveal_blocked' => $request->session()->get('reveal_blocked'),

            'marketplace' => [
                'interests_enabled' => (bool) config('marketplace.interests_enabled'),
            ],

            // The first-post dialog reads this. Setup is required until the
            // profile can actually post, and a superadmin never sees the prompt
            // because they manage other people's profiles rather than trade.
            'profile_setup' => function () use ($user): ?array {
                if (! $user) {
                    return null;
                }

                $profile = $user->profile;
                $readiness = app(MarketplaceReadiness::class)->for($user);

                return [
                    'required' => ! $user->is_superadmin
                        && ! $readiness['ready']['posts_freight'],
                    'profile' => [
                        'display_name' => $profile?->display_name ?? $user->name,
                        'account_type' => $profile?->account_type,
                        'phone' => $profile?->phone,
                        'provides_transport' => (bool) $profile?->provides_transport,
                        'primary_location_id' => $profile?->primary_location_id,
                    ],
                ];
            },

            // The contact released by the action the user just took, so it
            // survives the redirect back.
            'revealed_contact' => $request->session()->get('revealed_contact'),

            // The admin sidebar badge. Counted only for a superadmin, because
            // resolving the count for an ordinary user would be a query on every
            // page of the application to answer a question they cannot act on.
            'pending_manual_payments' => fn () => $this->pendingManualPayments($request),
            'announcement' => Announcement::currentlyActive()->latest()->first()?->only('id', 'title', 'body', 'type', 'link_text', 'link_url', 'is_dismissible'),
            'seo' => fn () => SeoMetadata::forPath($request->path())?->only('title', 'description', 'keywords', 'og_title', 'og_description', 'og_image', 'og_type', 'twitter_card', 'twitter_site', 'twitter_creator', 'twitter_image'),
            'features' => FeatureService::forFrontend(),
        ];
    }
}
