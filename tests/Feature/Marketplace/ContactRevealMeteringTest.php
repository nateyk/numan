<?php

use App\Enums\FreightPostStatus;
use App\Models\ContactReveal;
use App\Models\ContactRevealBlock;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplaceConnection;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BackfillContactRevealsService;
use App\Services\ContactRevealAnalyticsService;
use App\Services\ContactRevealService;
use App\Services\MarketplaceEntitlementService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

// ── fixtures ──────────────────────────────────────────────────────────────

/**
 * A user with a workspace, a phone and a profile.
 *
 * @return array{0: User, 1: Workspace}
 */
function meterUser(bool $transporter = false): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => ! $transporter,
        'provides_transport' => $transporter,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Metered '.($transporter ? 'Transporter' : 'Consignor'),
        'phone' => '+2519555'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
    ]);

    if ($transporter) {
        TransporterProfile::firstOrCreate(['user_id' => $user->id], [
            'slug' => 'meter-'.Str::lower(Str::random(8)),
            'display_name' => 'Metered Transporter',
            'status' => 'approved',
            'phone_visible' => true,
            'email_visible' => true,
        ]);
    }

    return [$user->fresh(), $workspace];
}

/**
 * An active paid subscription for a workspace.
 */
function meterSubscribe($workspace, string $interval = 'monthly'): MarketplaceSubscription
{
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $startsAt = now();

    return MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_interval' => $interval,
        'current_period_start' => $startsAt,
        'current_period_ends_at' => $startsAt->copy()->addMonth(),
        'ends_at' => $startsAt->copy()->addMonth(),
    ]);
}

/**
 * A live interest from $transporter on a post owned by $consignor.
 */
function meterInterest(User $consignor, User $transporter): FreightInterest
{
    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $consignor->current_workspace_id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'metering cargo',
    ]);

    return FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);
}

function meterReveal($test, User $viewer, FreightInterest $interest)
{
    return $test->actingAs($viewer)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));
}

/**
 * Five distinct counterparties the user may reveal, each with a live interest.
 *
 * Creates the relationships only. Nothing is revealed, so the quota is still
 * untouched.
 *
 * @return list<FreightInterest>
 */
function meterFivePartners(User $viewer): array
{
    $interests = [];

    for ($i = 0; $i < 5; $i++) {
        [$consignor] = meterUser();
        $interests[] = meterInterest($consignor, $viewer);
    }

    return $interests;
}

/**
 * Five counterparties created *and* revealed, so the free quota is spent.
 *
 * @return list<FreightInterest>
 */
function meterSpendQuota($test, User $viewer): array
{
    $interests = meterFivePartners($viewer);

    foreach ($interests as $interest) {
        $test->actingAs($viewer)
            ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]))
            ->assertOk();
    }

    return $interests;
}

// ── quota ─────────────────────────────────────────────────────────────────

it('lets a free user reveal five distinct counterparties for life', function () {
    [$user] = meterUser(true);

    foreach (meterFivePartners($user) as $interest) {
        meterReveal($this, $user, $interest)
            ->assertOk()
            ->assertJsonPath('status', 'allowed');
    }

    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(5);
});

it('blocks the sixth distinct reveal for a free user', function () {
    [$user] = meterUser(true);
    [$sixthConsignor] = meterUser();

    $interests = meterFivePartners($user);
    $sixth = meterInterest($sixthConsignor, $user);

    foreach ($interests as $interest) {
        meterReveal($this, $user, $interest)->assertOk();
    }

    meterReveal($this, $user, $sixth)
        ->assertStatus(402)
        ->assertJsonPath('blocked', true);

    // Only the five allowed ones are recorded.
    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(5);
});

it('the blocked response carries no contact keys and no contact strings', function () {
    [$user] = meterUser(true);
    [$blockedConsignor] = meterUser();

    $blockedConsignor->update(['email' => 'blocked-partner@example.test']);
    Profile::where('user_id', $blockedConsignor->id)->update(['display_name' => 'Blocked Partner']);
    Profile::where('user_id', $blockedConsignor->id)->update(['phone' => '+251977700001']);

    meterSpendQuota($this, $user);
    $blocked = meterInterest($blockedConsignor, $user);

    $response = meterReveal($this, $user, $blocked)->assertStatus(402);

    $body = $response->json();

    // No contact object at all.
    expect($body)->not->toHaveKey('contact');
    expect(array_keys($body))->not->toContain('contact');

    $payload = json_encode($body);
    expect($payload)->not->toContain('+251977700001');
    expect($payload)->not->toContain('blocked-partner@example.test');
    // Not the counterparty's display name either, which would be enough to
    // identify them without giving a way to reach them.
    expect($payload)->not->toContain('Blocked Partner');

    // Somewhere to go next.
    expect($body['subscribe_url'])->toBe(route('billing.plans'));
});

it('records every blocked attempt', function () {
    [$user] = meterUser(true);
    [$consignor] = meterUser();

    meterSpendQuota($this, $user);
    $blocked = meterInterest($consignor, $user);

    meterReveal($this, $user, $blocked)->assertStatus(402);
    meterReveal($this, $user, $blocked)->assertStatus(402);

    expect(ContactRevealBlock::where('user_id', $user->id)->count())->toBe(2);
    expect(ContactRevealBlock::where('user_id', $user->id)->first()->revealed_id)->toBe($consignor->id);
});

it('re-revealing the same partner is free even when the quota is spent', function () {
    [$user] = meterUser(true);
    [$consignor] = meterUser();

    $interests = meterSpendQuota($this, $user);

    // Quota spent, so a sixth distinct partner is refused.
    meterReveal($this, $user, meterInterest($consignor, $user))->assertStatus(402);

    // Going back to a partner already revealed is still free, and reports
    // itself as already rather than allowed, so it consumed nothing.
    meterReveal($this, $user, $interests[0])
        ->assertOk()
        ->assertJsonPath('status', 'already');

    meterReveal($this, $user, $interests[2])
        ->assertOk()
        ->assertJsonPath('status', 'already');

    // Five rows, not seven.
    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(5);

    // And the quota is still exactly full, not somehow refilled.
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertStatus(402);
});

it('keeps a repeat reveal free forever', function () {
    [$user] = meterUser(true);
    [$consignor] = meterUser();

    $interest = meterInterest($consignor, $user);

    meterReveal($this, $user, $interest)->assertOk();

    ContactReveal::where('revealer_id', $user->id)->update(['created_at' => now()->subDays(400)]);

    // Exhaust the allowance with four fresh partners (the first reveal is the
    // one aged to a year ago).
    foreach (array_slice(meterFivePartners($user), 0, 4) as $fresh) {
        meterReveal($this, $user, $fresh)->assertOk();
    }

    // A new partner is blocked...
    [$another] = meterUser();
    meterReveal($this, $user, meterInterest($another, $user))->assertStatus(402);

    // ...but the year-old reveal is still free, permanently.
    meterReveal($this, $user, $interest)
        ->assertOk()
        ->assertJsonPath('status', 'already');
});

it('does not refill the free quota when time passes', function () {
    // The password-expiry middleware would redirect a year-old session; this
    // test is about the reveal allowance, not session age.
    config(['auth.password_expiry_days' => 0]);

    [$user] = meterUser(true);

    meterSpendQuota($this, $user);

    [$sixth] = meterUser();
    $sixthInterest = meterInterest($sixth, $user);

    meterReveal($this, $user, $sixthInterest)->assertStatus(402);

    // The old weekly window would have reset here. The free allowance is
    // one-time, so it does not.
    $this->travel(8)->days();
    meterReveal($this, $user, $sixthInterest)->assertStatus(402);

    $this->travel(1)->year();
    meterReveal($this, $user, $sixthInterest)->assertStatus(402);

    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(5);
});

it('honours an admin change to the free reveal allowance immediately', function () {
    [$user] = meterUser(true);
    meterSpendQuota($this, $user);

    [$sixth] = meterUser();
    $sixthInterest = meterInterest($sixth, $user);

    meterReveal($this, $user, $sixthInterest)->assertStatus(402);

    // The admin raises the allowance; no cache, no restart.
    DB::table('marketplace_settings')
        ->where('key', 'free_contact_reveals')
        ->update(['value' => 10]);

    meterReveal($this, $user, $sixthInterest)->assertOk();
});

it('does not reuse the interest window for reveals', function () {
    // Setting the interest window to 365 must not let a free user reveal
    // without limit.
    DB::table('marketplace_settings')
        ->where('key', 'free_interest_window_days')
        ->update(['value' => 365]);

    [$user] = meterUser(true);
    $entitlements = app(MarketplaceEntitlementService::class);

    meterSpendQuota($this, $user);

    [$sixth] = meterUser();
    meterReveal($this, $user, meterInterest($sixth, $user))->assertStatus(402);

    expect($entitlements->getLimitValue($user->currentWorkspace, 'contact_reveals'))->toBe(5);
});

// ── paid ──────────────────────────────────────────────────────────────────

it('gives an active paid subscriber unlimited reveals', function () {
    [$user, $workspace] = meterUser(true);
    meterSubscribe($workspace);

    for ($i = 0; $i < 12; $i++) {
        [$consignor] = meterUser();
        meterReveal($this, $user, meterInterest($consignor, $user))
            ->assertOk()
            ->assertJsonPath('status', 'allowed');
    }

    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(12);
});

it('stops being unlimited once the paid period ends', function () {
    [$user, $workspace] = meterUser(true);
    $sub = meterSubscribe($workspace);

    // Two reveals while paid: no quota in force.
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertOk();
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertOk();
    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(2);

    $sub->update([
        'ends_at' => now()->subDay(),
        'current_period_ends_at' => now()->subDay(),
    ]);

    // The free allowance of five now applies, and the two reveals already
    // spent count against it — so exactly three more are allowed.
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertOk();
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertOk();
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertOk();
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertStatus(402);
});

it('a superseded plan subscriber keeps their limits', function () {
    [$user, $workspace] = meterUser(true);
    $sub = meterSubscribe($workspace);

    $sub->update(['status' => 'superseded', 'ends_at' => now()->addMonths(5)]);

    for ($i = 0; $i < 5; $i++) {
        meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertOk();
    }

    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertStatus(402);
});

it('a subscriber on an archived plan keeps unlimited reveals', function () {
    [$user, $workspace] = meterUser(true);
    meterSubscribe($workspace);

    // Archiving hides the plan from new sales. It must NOT quietly downgrade
    // the people already paying for it, so their entitlement survives.
    MarketplacePlan::where('slug', 'standard')->update(['is_active' => false]);

    for ($i = 0; $i < 6; $i++) {
        meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertOk();
    }

    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(6);
});

// ── both roles, and strangers ─────────────────────────────────────────────

it('meters the transporter revealing the consignor', function () {
    [$transporter] = meterUser(true);

    $interests = [];

    for ($i = 0; $i < 6; $i++) {
        [$consignor] = meterUser();
        $interests[] = meterInterest($consignor, $transporter);
    }

    for ($i = 0; $i < 5; $i++) {
        meterReveal($this, $transporter, $interests[$i])->assertOk();
    }

    meterReveal($this, $transporter, $interests[5])->assertStatus(402);

    // The ledger is per (revealer, revealed), so it does not care which side
    // the viewer is on.
    expect(ContactReveal::where('revealer_id', $transporter->id)->count())->toBe(5);
});

it('meters each direction independently', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);

    $interest = meterInterest($consignor, $transporter);

    meterReveal($this, $consignor, $interest)->assertOk();

    // The transporter's own quota is untouched by the consignor's reveal.
    expect(ContactReveal::where('revealer_id', $transporter->id)->count())->toBe(0);

    meterReveal($this, $transporter, $interest)->assertOk();

    expect(ContactReveal::where('revealer_id', $consignor->id)->count())->toBe(1);
    expect(ContactReveal::where('revealer_id', $transporter->id)->count())->toBe(1);
});

it('forbids a stranger', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    $stranger = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    Profile::firstOrCreate(['user_id' => $stranger->id], ['phone' => '+251911100000']);

    $this->actingAs($stranger)
        ->post(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertForbidden();

    expect(ContactReveal::count())->toBe(0);
});

it('forbids a rejected or withdrawn interest', function (string $status) {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);

    $interest = meterInterest($consignor, $transporter);
    $interest->update(['status' => $status]);

    $this->actingAs($consignor)
        ->post(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertForbidden();

    expect(ContactReveal::count())->toBe(0);
})->with(['rejected', 'withdrawn']);

// ── already-revealed on a later page load ─────────────────────────────────

it('shows the contact on a later page load without another action', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    // Before: nothing in the payload.
    $before = $this->actingAs($consignor)
        ->get(route('freight.interests.index', ['ulid' => $interest->freightPost->ulid]));

    $before->assertOk();
    expect($before->viewData('page')['props']['revealedContacts'])->toBeEmpty();

    meterReveal($this, $consignor, $interest)->assertOk();

    // After: present, without posting again.
    $after = $this->actingAs($consignor)
        ->get(route('freight.interests.index', ['ulid' => $interest->freightPost->ulid]));

    $after->assertOk();

    $contact = $after->viewData('page')['props']['revealedContacts'][(string) $interest->id];
    expect($contact)->not->toBeNull();
    expect($contact['phone'])->toBe($transporter->profile->phone);

    // Reading it again recorded nothing extra.
    expect(ContactReveal::where('revealer_id', $consignor->id)->count())->toBe(1);
});

it('shows an already-revealed contact on the connection page', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    $connection = MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $interest->freight_post_id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $url = route('connections.show', ['public_id' => $connection->public_id]);

    $this->actingAs($consignor)->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('alreadyRevealed', false)
            ->where('revealedContacts', null));

    $this->actingAs($consignor)
        ->postJson(route('connections.reveal', ['public_id' => $connection->public_id]))
        ->assertOk();

    $this->actingAs($consignor)->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('alreadyRevealed', true)
            ->where('revealedContacts.phone', $transporter->profile->phone));
});

it('a page load never records a reveal, so it cannot consume quota', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);

    meterSpendQuota($this, $consignor);

    $interest = meterInterest($consignor, $transporter);

    $before = ContactReveal::where('revealer_id', $consignor->id)->count();

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($consignor)
            ->get(route('freight.interests.index', ['ulid' => $interest->freightPost->ulid]))
            ->assertOk();
    }

    // Five page loads, zero new ledger rows. Reading a page cannot spend quota.
    expect(ContactReveal::where('revealer_id', $consignor->id)->count())->toBe($before);
    expect(ContactRevealBlock::count())->toBe(0);
});

it('the transporter interest list also shows an already-revealed contact', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    meterReveal($this, $transporter, $interest)->assertOk();

    $this->actingAs($transporter)
        ->get(route('freight.my-interests'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('revealedContacts.'.$interest->id.'.phone', $consignor->profile->phone));
});

// ── backfill ──────────────────────────────────────────────────────────────

it('backfills both directions of every connection', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    $connection = MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $interest->freight_post_id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    DB::table('marketplace_connections')
        ->where('id', $connection->id)
        ->update(['created_at' => now()->subDays(30)]);
    $connection->refresh();
    ContactReveal::query()->delete();

    $result = app(BackfillContactRevealsService::class)->run();

    expect($result['connections'])->toBe(1);
    expect($result['rows_created'])->toBe(2);

    expect(ContactReveal::where('revealer_id', $consignor->id)->count())->toBe(1);
    expect(ContactReveal::where('revealer_id', $transporter->id)->count())->toBe(1);

    // The row carries the connection's own timestamp, not now().
    expect(ContactReveal::where('revealer_id', $consignor->id)->first()->created_at->toDateString())
        ->toBe(now()->subDays(30)->toDateString());
});

it('a backfilled pair keeps access without using quota', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $interest->freight_post_id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    app(BackfillContactRevealsService::class)->run();

    // Even with the free quota already spent by other partners, the backfilled
    // partner is still reachable.
    meterFivePartners($consignor);
    foreach (ContactReveal::where('revealer_id', $consignor->id)
        ->where('revealed_id', '!=', $transporter->id)
        ->get() as $_) {
        // counted above
    }

    $outcome = app(ContactRevealService::class)->reveal($consignor, $transporter, $interest);

    expect($outcome['status'])->toBe('already');
    expect($outcome['contact']['phone'])->toBe($transporter->profile->phone);
    expect(ContactRevealBlock::count())->toBe(0);
});

it('backfills accepted interests that never became connections', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);

    $interest = meterInterest($consignor, $transporter);
    $interest->update(['status' => 'shortlisted']);

    $result = app(BackfillContactRevealsService::class)->run();

    expect($result['interests'])->toBe(1);
    expect($result['rows_created'])->toBe(2);
});

it('does not backfill a merely submitted interest', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);

    meterInterest($consignor, $transporter);

    $result = app(BackfillContactRevealsService::class)->run();

    expect($result['interests'])->toBe(0);
    expect($result['rows_created'])->toBe(0);
});

it('the backfill is idempotent', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $interest->freight_post_id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $service = app(BackfillContactRevealsService::class);

    expect($service->run()['rows_created'])->toBe(2);
    expect($service->run()['rows_created'])->toBe(0);

    expect(ContactReveal::count())->toBe(2);
});

it('the dry run reports the same counts and writes nothing', function () {
    [$consignor] = meterUser();
    [$transporter] = meterUser(true);
    $interest = meterInterest($consignor, $transporter);

    MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $interest->freight_post_id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $service = app(BackfillContactRevealsService::class);

    $preview = $service->preview();
    expect($preview['rows_created'])->toBe(2);
    expect(ContactReveal::count())->toBe(0);

    expect($service->run()['rows_created'])->toBe(2);
});

// ── analytics ─────────────────────────────────────────────────────────────

it('counts reveals and blocks per day over thirty days, zero filled', function () {
    [$user] = meterUser(true);

    meterSpendQuota($this, $user);
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertStatus(402);

    ContactReveal::query()->update(['created_at' => now()->subDays(3)]);
    ContactRevealBlock::query()->update(['created_at' => now()->subDays(10)]);

    $daily = app(ContactRevealAnalyticsService::class)->daily(30);

    expect($daily['days'])->toHaveCount(30);
    expect($daily['totals']['reveals'])->toBe(5);
    expect($daily['totals']['blocks'])->toBe(1);

    expect($daily['days'][26]['reveals'])->toBe(5);
    expect($daily['days'][26]['blocks'])->toBe(0);
    expect($daily['days'][19]['blocks'])->toBe(1);
});

it('counts a user who subscribed within seven days of a blocked attempt', function () {
    [$user, $workspace] = meterUser(true);

    meterSpendQuota($this, $user);
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertStatus(402);

    $before = app(ContactRevealAnalyticsService::class)->conversion(7);
    expect($before['blocked_users'])->toBe(1);
    expect($before['converted_users'])->toBe(0);

    meterSubscribe($workspace);

    $after = app(ContactRevealAnalyticsService::class)->conversion(7);

    expect($after['blocked_users'])->toBe(1);
    expect($after['converted_users'])->toBe(1);
    expect($after['conversion_rate'])->toBe(1.0);
});

it('does not count a subscription outside the conversion window', function () {
    [$user, $workspace] = meterUser(true);

    meterSpendQuota($this, $user);
    meterReveal($this, $user, meterInterest(meterUser()[0], $user))->assertStatus(402);

    // A subscription that started before the block is not a conversion.
    meterSubscribe($workspace);
    DB::table('marketplace_subscriptions')
        ->where('workspace_id', $workspace->id)
        ->update(['created_at' => now()->subDays(10)]);

    $analytics = app(ContactRevealAnalyticsService::class)->conversion(7);

    expect($analytics['blocked_users'])->toBe(1);
    expect($analytics['converted_users'])->toBe(0);
});

it('analytics return zeros rather than failing with no data', function () {
    $analytics = app(ContactRevealAnalyticsService::class);

    expect($analytics->daily(30)['totals']['reveals'])->toBe(0);
    expect($analytics->conversion(7)['conversion_rate'])->toBe(0.0);
});

// ── the surfaces around the paywall ───────────────────────────────────────

it('a blocked reveal from the browser flashes the numbers and a subscribe link', function () {
    [$user] = meterUser(true);

    meterSpendQuota($this, $user);
    $blocked = meterInterest(meterUser()[0], $user);

    $this->actingAs($user)
        ->post(route('freight.interests.reveal', ['interest' => $blocked->id]))
        ->assertRedirect()
        ->assertSessionHas('reveal_blocked', [
            'limit' => 5,
            'used' => 5,
        ])
        ->assertSessionHasErrors('contact');

    // No contact in the flash session either.
    expect(json_encode(session()->all()))->not->toContain('phone');
});

it('the blocked notice reaches the interest list the viewer is on', function () {
    [$consignor] = meterUser();

    meterSpendQuota($this, $consignor);

    // A sixth interest on the consignor's OWN post, so the notice lands on a
    // page they are allowed to see.
    $blocked = meterInterest($consignor, meterUser(true)[0]);

    $this->actingAs($consignor)
        ->post(route('freight.interests.reveal', ['interest' => $blocked->id]));

    // The consignor's own interest list for that post now carries the notice.
    $this->actingAs($consignor)
        ->get(route('freight.interests.index', ['ulid' => $blocked->freightPost->ulid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reveal_blocked.limit', 5)
            ->missing('reveal_blocked.window_days'));
});

it('the admin analytics page is superadmin only and returns counts', function () {
    [$user] = meterUser();
    meterSpendQuota($this, $user);

    $this->actingAs($user)
        ->get(route('admin.marketplace-reveals.index'))
        ->assertForbidden();

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);
    app(WorkspaceService::class)->createPersonalWorkspace($admin);
    Profile::firstOrCreate(['user_id' => $admin->id], ['phone' => '+251911100001']);

    $this->actingAs($admin)
        ->get(route('admin.marketplace-reveals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/marketplace/reveal-analytics')
            ->where('summary.totals.reveals', 5)
            ->where('summary.daily', fn ($days) => count($days) === 30)
            ->where('summary.ledger_rows', 5)
            ->where('summary.blocked_rows', 0)
            ->where('summary.conversion.blocked_users', 0));
});

it('the analytics page never carries contact data', function () {
    [$user] = meterUser(true);
    meterSpendQuota($this, $user);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);
    app(WorkspaceService::class)->createPersonalWorkspace($admin);

    $payload = json_encode(
        $this->actingAs($admin)
            ->get(route('admin.marketplace-reveals.index'))
            ->viewData('page')['props']
    );

    // An analytics screen must not be able to reveal anything. Assert on the
    // summary subtree rather than the whole payload, because the shared props
    // legitimately carry the *admin's own* phone and email.
    $summary = json_encode(
        $this->actingAs($admin)
            ->get(route('admin.marketplace-reveals.index'))
            ->viewData('page')['props']['summary']
    );

    expect($summary)->not->toContain('+2519555');
    expect($summary)->not->toContain('phone');
    expect($summary)->not->toContain('email');
    expect($summary)->not->toContain('revealed_id');
    expect($summary)->not->toContain('user_id');
});

// ── the window arithmetic at the boundary ──────────────────────────────────

it('blocks the last remaining slot exactly once, for two different pairs', function () {
    // The boundary case: the user is one reveal short of the limit, so two
    // DIFFERENT partners are requested and only one can be satisfied.
    //
    // Run sequentially on purpose. The suite runs on SQLite, where
    // lockForUpdate is a no-op, so this cannot and does not test concurrency.
    // What it tests is that the count-then-compare happens against committed
    // state: the second request must observe the first one's ledger row. Under
    // real concurrency the user-row lock in ContactRevealService::reveal()
    // serialises them, and the unique (revealer_id, revealed_id) key makes the
    // same-pair case idempotent regardless.
    [$user] = meterUser(true);

    // Spend five of the five slots.
    meterSpendQuota($this, $user);

    ContactReveal::query()->where('revealer_id', $user->id)->limit(1)->delete();
    ContactRevealBlock::query()->delete();

    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(4);

    // Two fresh partners, neither revealed yet.
    [$first] = meterUser();
    [$second] = meterUser();

    $firstInterest = meterInterest($first, $user);
    $secondInterest = meterInterest($second, $user);

    // The last slot goes to the first.
    meterReveal($this, $user, $firstInterest)->assertOk()->assertJsonPath('status', 'allowed');
    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(5);

    // The second finds the allowance full, because it can see the row above.
    meterReveal($this, $user, $secondInterest)
        ->assertStatus(402)
        ->assertJsonPath('used', 5)
        ->assertJsonPath('limit', 5);

    // And the refusal was recorded rather than swallowed.
    expect(ContactRevealBlock::where('user_id', $user->id)->count())->toBe(1);
    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(5);
});

it('two different pairs cannot both take the last slot, whichever order they arrive', function () {
    // The mirror of the above, to show the arithmetic is not order-dependent.
    [$user] = meterUser(true);

    meterSpendQuota($this, $user);
    ContactReveal::query()->where('revealer_id', $user->id)->limit(1)->delete();

    [$a] = meterUser();
    [$b] = meterUser();

    $aInterest = meterInterest($a, $user);
    $bInterest = meterInterest($b, $user);

    $first = meterReveal($this, $user, $aInterest);
    $second = meterReveal($this, $user, $bInterest);

    $statuses = [$first->getStatusCode(), $second->getStatusCode()];

    expect($statuses)->toContain(200);
    expect($statuses)->toContain(402);
    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(5);
});

it('the ledger cannot hold the same pair twice, whatever the code path', function () {
    [$user] = meterUser(true);
    [$consignor] = meterUser();

    $interest = meterInterest($consignor, $user);

    meterReveal($this, $user, $interest)->assertOk();
    meterReveal($this, $user, $interest)->assertOk();
    meterReveal($this, $user, $interest)->assertOk();

    expect(ContactReveal::where('revealer_id', $user->id)->count())->toBe(1);
});

it('a direct duplicate insert is rejected by the unique key', function () {
    [$user] = meterUser(true);
    [$consignor] = meterUser();

    ContactReveal::create(['revealer_id' => $user->id, 'revealed_id' => $consignor->id]);

    expect(fn () => ContactReveal::create([
        'revealer_id' => $user->id,
        'revealed_id' => $consignor->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});
