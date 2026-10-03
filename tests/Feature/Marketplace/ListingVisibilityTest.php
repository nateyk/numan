<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\FreightPostsHiddenNotification;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceFulfillmentService;
use App\Services\MarketplaceListingVisibilityService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->visibility = app(MarketplaceListingVisibilityService::class);
});

function ownerWithWorkspace(): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    return [$user, $workspace];
}

function makeLivePost(Workspace $workspace, User $owner, FreightPostStatus $status = FreightPostStatus::Active): FreightPost
{
    return FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'status' => $status->value,
        'cargo_description' => 'cargo',
    ]);
}

function grantLapsedSubscription(Workspace $workspace, User $user): MarketplaceSubscription
{
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    return MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(40),
        'current_period_ends_at' => now()->subDays(10),
        'ends_at' => now()->subDays(10),
        'provider' => 'chapa',
    ]);
}

// ── expiry ──────────────────────────────────────────────────────────────

it('marks a lapsed subscription expired and leaves superseded alone', function () {
    [$user, $workspace] = ownerWithWorkspace();

    grantLapsedSubscription($workspace, $user);

    $superseded = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'status' => 'superseded',
        'billing_interval' => 'weekly',
        'current_period_start' => now()->subDays(20),
        'current_period_ends_at' => now()->subDays(13),
        'ends_at' => now()->subDays(13),
        'provider' => 'chapa',
    ]);

    $expired = $this->visibility->expireLapsedSubscriptions();

    expect($expired)->toBe(1);
    expect(MarketplaceSubscription::find($superseded->id)->status)->toBe('superseded');
    expect(
        MarketplaceSubscription::where('workspace_id', $workspace->id)->where('status', 'active')->count()
    )->toBe(0);
});

it('does not expire a subscription whose ends_at is still in the future', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    MarketplaceSubscription::where('workspace_id', $workspace->id)->update([
        'current_period_ends_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5),
    ]);

    expect($this->visibility->expireLapsedSubscriptions())->toBe(0);
    expect(
        MarketplaceSubscription::where('workspace_id', $workspace->id)->where('status', 'active')->count()
    )->toBe(1);
});

// ── hiding ──────────────────────────────────────────────────────────────

it('hides the excess live posts and keeps the newest', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $oldest = makeLivePost($workspace, $user);
    $oldest->update(['created_at' => now()->subDays(3)]);

    $middle = makeLivePost($workspace, $user);
    $middle->update(['created_at' => now()->subDays(2)]);

    $newest = makeLivePost($workspace, $user);
    $newest->update(['created_at' => now()->subDay()]);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($newest->fresh()->hidden_at)->toBeNull();
    expect($middle->fresh()->hidden_at)->not->toBeNull();
    expect($oldest->fresh()->hidden_at)->not->toBeNull();
});

it('keeps every post when the count is within the free limit', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $post = makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($post->fresh()->hidden_at)->toBeNull();
});

it('never changes a post status when hiding', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $first = makeLivePost($workspace, $user);
    $first->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($first->fresh()->status)->toBe(FreightPostStatus::Active->value);
});

it('closing a post frees a slot so it is not counted', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $closed = makeLivePost($workspace, $user, FreightPostStatus::Closed);
    $live = makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($closed->fresh()->hidden_at)->toBeNull();
    expect($live->fresh()->hidden_at)->toBeNull();
});

it('is idempotent when run twice', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    $firstHiddenAt = $old->fresh()->hidden_at->toDateTimeString();

    $this->visibility->hideExcessPosts();
    $this->visibility->hideExcessPosts();

    // The timestamp is not moved on later runs.
    expect($old->fresh()->hidden_at->toDateTimeString())->toBe($firstHiddenAt);
    expect(FreightPost::where('workspace_id', $workspace->id)->hidden()->count())->toBe(1);
    expect(
        MarketplaceSubscription::where('workspace_id', $workspace->id)->where('status', 'expired')->count()
    )->toBe(1);
});

// ── decision 2: hidden posts do not count toward the limit ───────────────

it('hidden posts do not count toward the free live post limit', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($old->fresh()->hidden_at)->not->toBeNull();

    // One visible live post remains, which is exactly the free limit.
    expect(app(MarketplaceEntitlementService::class)->livePostCount($workspace))->toBe(1);
    expect(app(MarketplaceEntitlementService::class)->canCreateFreightPost($workspace))->toBeFalse();
});

it('the hide pass does not hide a post just because hidden ones exist', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(5)]);
    makeLivePost($workspace, $user);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    $hiddenCount = FreightPost::where('workspace_id', $workspace->id)->hidden()->count();

    // Free limit is 1, so 2 are hidden and 1 visible. A second pass changes nothing.
    expect($hiddenCount)->toBe(2);

    $visible = FreightPost::where('workspace_id', $workspace->id)->visible()->count();
    expect($visible)->toBe(1);
});

// ── A: renewal case ─────────────────────────────────────────────────────

it('hides nothing when the workspace has an active subscription', function () {
    [$user, $workspace] = ownerWithWorkspace();

    // An old expired row plus a new active row: the renewal case.
    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'status' => 'expired',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(40),
        'current_period_ends_at' => now()->subDays(10),
        'ends_at' => now()->subDays(10),
        'provider' => 'chapa',
    ]);

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'weekly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(7),
        'ends_at' => now()->addDays(7),
        'provider' => 'chapa',
    ]);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->hideExcessPosts();

    expect(FreightPost::where('workspace_id', $workspace->id)->hidden()->count())->toBe(0);
});

// ── H: grandfathering ───────────────────────────────────────────────────

it('never hides a workspace that never had a paid subscription', function () {
    [$user, $workspace] = ownerWithWorkspace();

    makeLivePost($workspace, $user);
    makeLivePost($workspace, $user);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect(FreightPost::where('workspace_id', $workspace->id)->hidden()->count())->toBe(0);
});

it('never hides a workspace whose only subscription is superseded', function () {
    [$user, $workspace] = ownerWithWorkspace();

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'status' => 'superseded',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(60),
        'current_period_ends_at' => now()->subDays(30),
        'ends_at' => now()->subDays(30),
        'provider' => 'chapa',
    ]);

    makeLivePost($workspace, $user);
    makeLivePost($workspace, $user);

    $this->visibility->hideExcessPosts();

    expect(FreightPost::where('workspace_id', $workspace->id)->hidden()->count())->toBe(0);
});

// ── B: ownership resolution ──────────────────────────────────────────────

it('resolves posts by workspace_id', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($old->fresh()->hidden_at)->not->toBeNull();
});

it('falls back to user_id for posts with a null workspace_id', function () {
    [$user, $workspace] = ownerWithWorkspace();
    $workspace->addUser($user, 'owner');
    grantLapsedSubscription($workspace, $user);

    $legacy = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $user->id,
        'workspace_id' => null,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'legacy',
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ]);

    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($legacy->fresh()->hidden_at)->not->toBeNull();
});

it('does not hide another users post with a null workspace_id', function () {
    [$user, $workspace] = ownerWithWorkspace();
    $workspace->addUser($user, 'owner');
    grantLapsedSubscription($workspace, $user);

    $stranger = User::factory()->create();
    $strangerPost = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $stranger->id,
        'workspace_id' => null,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'someone else',
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ]);

    makeLivePost($workspace, $user);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect($strangerPost->fresh()->hidden_at)->toBeNull();
});

// ── F: notification ─────────────────────────────────────────────────────

it('notifies the owner once with how many posts were hidden', function () {
    Notification::fake();

    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    Notification::assertSentTo(
        $user,
        FreightPostsHiddenNotification::class,
        fn ($n) => $n->hiddenCount === 2
            && $n->toArray($user)['url'] === '/billing'
            && str_contains($n->toArray($user)['message'], '2 freight posts')
    );
    Notification::assertSentToTimes($user, FreightPostsHiddenNotification::class, 1);
});

it('does not notify again on a later run', function () {
    Notification::fake();

    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    $this->visibility->hideExcessPosts();

    Notification::assertSentToTimes($user, FreightPostsHiddenNotification::class, 1);
});

// ── G: fulfilment restores, and a failure does not ──────────────────────

it('a paid fulfilment clears hidden_at for the workspace', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    expect(FreightPost::where('workspace_id', $workspace->id)->hidden()->count())->toBe(1);

    $plan = MarketplacePlan::where('slug', 'standard')->first();
    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-standard_restore',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
    ]);

    app(MarketplaceFulfillmentService::class)->fulfill($tx, [
        'status' => 'success',
        'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 449, 'reference' => 'ref'],
    ]);

    expect(FreightPost::where('workspace_id', $workspace->id)->hidden()->count())->toBe(0);
});

it('a failed fulfilment leaves hidden posts hidden', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->visibility->expireLapsedSubscriptions();
    $this->visibility->hideExcessPosts();

    $hiddenBefore = FreightPost::where('workspace_id', $workspace->id)->hidden()->count();
    expect($hiddenBefore)->toBe(1);

    // Make the restore step blow up so the whole grant rolls back.
    $this->mock(MarketplaceListingVisibilityService::class, function ($mock): void {
        $mock->shouldReceive('restoreHiddenPosts')->andThrow(new RuntimeException('restore failed'));
    });

    $plan = MarketplacePlan::where('slug', 'standard')->first();
    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-standard_fail',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'mode' => 'test',
    ]);

    expect(fn () => app(MarketplaceFulfillmentService::class)->fulfill($tx, [
        'status' => 'success',
        'data' => ['status' => 'success', 'currency' => 'ETB', 'amount' => 449, 'reference' => 'ref'],
    ]))->toThrow(RuntimeException::class);

    // Nothing was committed and the listings are still hidden.
    expect(FreightPost::where('workspace_id', $workspace->id)->hidden()->count())->toBe($hiddenBefore);
    expect(MarketplaceSubscription::where('chapa_tx_ref', 'NF-standard_fail')->exists())->toBeFalse();
    expect(MarketplacePaymentTransaction::find($tx->id)->status)->toBe('initialized');
});

// ── C: dry run changes nothing ──────────────────────────────────────────

it('the dry run writes nothing', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $subscriptionsBefore = MarketplaceSubscription::where('workspace_id', $workspace->id)
        ->get()->map(fn ($s) => [$s->id, $s->status, (string) $s->ends_at])->all();

    $this->artisan('marketplace:expire-subscriptions', ['--dry-run' => true])
        ->assertSuccessful();

    $subscriptionsAfter = MarketplaceSubscription::where('workspace_id', $workspace->id)
        ->get()->map(fn ($s) => [$s->id, $s->status, (string) $s->ends_at])->all();

    expect($subscriptionsAfter)->toEqual($subscriptionsBefore);
    expect($old->fresh()->hidden_at)->toBeNull();
    expect($old->fresh()->status)->toBe(FreightPostStatus::Active->value);
});

it('the dry run reports what would happen', function () {
    [$user, $workspace] = ownerWithWorkspace();
    grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->artisan('marketplace:expire-subscriptions', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('hide=1')
        ->assertSuccessful();
});

it('the real pass flips status and hides', function () {
    [$user, $workspace] = ownerWithWorkspace();
    $sub = grantLapsedSubscription($workspace, $user);

    $old = makeLivePost($workspace, $user);
    $old->update(['created_at' => now()->subDays(3)]);
    makeLivePost($workspace, $user);

    $this->artisan('marketplace:expire-subscriptions')
        ->assertSuccessful();

    expect(MarketplaceSubscription::find($sub->id)->status)->toBe('expired');
    expect($old->fresh()->hidden_at)->not->toBeNull();
});
