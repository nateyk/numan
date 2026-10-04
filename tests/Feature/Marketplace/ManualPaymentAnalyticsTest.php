<?php

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ActivityPropertyScrubber;
use App\Services\MarketplaceRevenueAnalyticsService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ETB revenue totals, the admin badge, the scrubber keys, and the billing
 * history section.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/**
 * @return array{0: User, 1: Workspace}
 */
function revenueUser(): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    Profile::firstOrCreate(['user_id' => $user->id], [
        'onboarding_step' => 'completed',
        'phone' => '+251911000111',
    ]);

    return [$user->fresh(), $workspace];
}

function revenueAdmin(): User
{
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($admin);
    $admin->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $admin->id], [
        'onboarding_step' => 'completed',
        'phone' => '+251911000000',
    ]);

    return $admin->fresh();
}

/**
 * A manual request still awaiting a decision.
 */
function pendingManualRow(string $interval, float $amount): MarketplacePaymentTransaction
{
    [$payer, $workspace] = revenueUser();

    return MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-PEND'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        'amount_etb' => $amount,
        'currency' => 'ETB',
        'billing_interval' => $interval,
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT'.random_int(100000, 999999),
        'manual_submitted_at' => now(),
        'manual_expires_at' => now()->addDays(13),
        'open_manual_workspace_id' => $workspace->id,
    ]);
}

/**
 * A verified transaction, which is the only thing that counts as revenue.
 *
 * @return array{0: User, 1: Workspace, 2: MarketplacePaymentTransaction}
 */
function revenueRow(string $provider, string $interval, float $amount, int $daysAgo = 0): array
{
    [$user, $workspace] = revenueUser();

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-REV'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        'amount_etb' => $amount,
        'currency' => 'ETB',
        'billing_interval' => $interval,
        'status' => MarketplacePaymentTransaction::MANUAL_CONFIRMED,
        'mode' => $provider === 'chapa' ? 'test' : 'manual',
        'provider' => $provider,
        'verified_at' => now()->subDays($daysAgo),
    ]);

    // verified_at is guarded by the timestamp trait on create, so set it again.
    DB::table('marketplace_payment_transactions')
        ->where('id', $transaction->id)
        ->update(['verified_at' => now()->subDays($daysAgo)]);

    return [$user, $workspace, $transaction->fresh()];
}

// ── K: the scrubber covers the new manual columns ─────────────────────────

it('treats the manual payment columns as sensitive', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    foreach (['manual_payer_phone', 'manual_reference', 'manual_proof_path'] as $key) {
        expect($scrubber->isSensitive($key))->toBeTrue();
        expect(ActivityPropertyScrubber::SENSITIVE_KEYS)->toContain($key);
    }
});

it('removes the manual payment columns from a properties payload', function () {
    $scrubber = app(ActivityPropertyScrubber::class);

    [$cleaned, $removed] = $scrubber->scrub([
        'attributes' => [
            'amount_etb' => '449.00',
            'manual_payer_phone' => '+251911223344',
            'manual_reference' => 'FT123456789',
            'manual_proof_path' => 'manual-payment-proofs/2026/10/abc.png',
        ],
        'old' => ['manual_payer_phone' => '+251911000000'],
    ]);

    expect($removed)->toBe(4);
    expect($cleaned['attributes'])->toBe(['amount_etb' => '449.00']);
    expect($cleaned['old'])->toBe([]);
});

it('the transaction model is not activity-logged', function () {
    // If this ever gains LogsActivity with logFillable, manual_payer_phone would
    // land in the audit trail exactly as the profile models' phone did.
    expect(class_uses_recursive(MarketplacePaymentTransaction::class))
        ->not->toContain(LogsActivity::class);

    expect(class_uses_recursive(MarketplacePaymentTransaction::class))
        ->not->toContain(LogsActivity::class);
});

it('no activity row ever holds a manual payer phone or bank reference', function () {
    revenueRow('manual', 'monthly', 449);

    $properties = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($properties)->not->toContain('+251911223344');
    expect($properties)->not->toContain('FT123456789');
});

// ── I: the admin badge ────────────────────────────────────────────────────

it('shares the pending count with a superadmin', function () {
    $admin = revenueAdmin();

    pendingManualRow('monthly', 449);
    pendingManualRow('yearly', 3999);
    // A decided one must not count.
    revenueRow('manual', 'monthly', 449);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('pending_manual_payments', 2));
});

it('does not count decided manual payments in the badge', function () {
    $admin = revenueAdmin();

    pendingManualRow('monthly', 449);
    revenueRow('manual', 'monthly', 449);
    revenueRow('chapa', 'weekly', 149);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('pending_manual_payments', 1));
});

it('gives an ordinary user a zero badge without a query', function () {
    $admin = revenueAdmin();
    pendingManualRow('monthly', 449);

    [$payer] = revenueUser();

    $this->actingAs($payer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('pending_manual_payments', 0));
});

it('the badge ignores Chapa transactions', function () {
    $admin = revenueAdmin();

    // A pending Chapa row, which must not appear on a manual badge.
    [$payer, $workspace] = revenueUser();
    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-CHAPA-PENDING-BADGE',
        'amount_etb' => 149,
        'currency' => 'ETB',
        'billing_interval' => 'weekly',
        'status' => 'pending',
        'mode' => 'test',
        'provider' => 'chapa',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('pending_manual_payments', 0));
});

// ── L: ETB revenue totals ─────────────────────────────────────────────────

it('sums verified revenue by provider', function () {
    revenueRow('chapa', 'weekly', 149);
    revenueRow('chapa', 'monthly', 449);
    revenueRow('manual', 'yearly', 3999);
    revenueRow('manual', 'monthly', 449);

    $totals = app(MarketplaceRevenueAnalyticsService::class)->totals();

    expect($totals['total_etb'])->toBe(5046.0);
    expect($totals['by_provider']['chapa'])->toBe(598.0);
    expect($totals['by_provider']['manual'])->toBe(4448.0);
    expect($totals['verified_count'])->toBe(4);
});

it('sums verified revenue by interval', function () {
    revenueRow('chapa', 'weekly', 149);
    revenueRow('chapa', 'monthly', 449);
    revenueRow('manual', 'yearly', 3999);

    $byInterval = app(MarketplaceRevenueAnalyticsService::class)->totals()['by_interval'];

    expect($byInterval['weekly'])->toBe(149.0);
    expect($byInterval['monthly'])->toBe(449.0);
    expect($byInterval['yearly'])->toBe(3999.0);
});

it('counts nothing that is not verified', function () {
    revenueRow('chapa', 'monthly', 449);

    // A pending Chapa transaction.
    [$payer, $workspace] = revenueUser();
    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-PENDINGCHAPA',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'pending',
        'mode' => 'test',
        'provider' => 'chapa',
    ]);

    // A pending manual request.
    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-PENDINGMANUAL',
        'amount_etb' => 3999,
        'currency' => 'ETB',
        'billing_interval' => 'yearly',
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-PENDING',
        'open_manual_workspace_id' => $workspace->id,
    ]);

    $totals = app(MarketplaceRevenueAnalyticsService::class)->totals();

    expect($totals['total_etb'])->toBe(449.0);
    expect($totals['verified_count'])->toBe(1);
    expect($totals['pending_count'])->toBe(2);
});

it('a rejected manual request is not revenue', function () {
    [, , $transaction] = revenueRow('manual', 'monthly', 449);

    DB::table('marketplace_payment_transactions')->where('id', $transaction->id)->update([
        'status' => MarketplacePaymentTransaction::MANUAL_REJECTED,
    ]);

    expect(app(MarketplaceRevenueAnalyticsService::class)->totals()['total_etb'])->toBe(0.0);
});

it('an expired manual request is not revenue', function () {
    [, , $transaction] = revenueRow('manual', 'monthly', 449);

    DB::table('marketplace_payment_transactions')->where('id', $transaction->id)->update([
        'status' => MarketplacePaymentTransaction::MANUAL_EXPIRED,
    ]);

    expect(app(MarketplaceRevenueAnalyticsService::class)->totals()['total_etb'])->toBe(0.0);
});

it('returns zeros rather than failing with no data', function () {
    $totals = app(MarketplaceRevenueAnalyticsService::class)->totals();

    expect($totals['total_etb'])->toBe(0.0);
    expect($totals['verified_count'])->toBe(0);
    expect(app(MarketplaceRevenueAnalyticsService::class)->daily(30))->toHaveCount(30);
    expect(app(MarketplaceRevenueAnalyticsService::class)->monthly(12))->toHaveCount(12);
    expect(app(MarketplaceRevenueAnalyticsService::class)->byPlan())->toBe([]);
});

it('zero-fills the daily series so a quiet day is a zero', function () {
    revenueRow('manual', 'monthly', 449, daysAgo: 3);

    $daily = app(MarketplaceRevenueAnalyticsService::class)->daily(30);

    expect($daily)->toHaveCount(30);
    expect($daily[26]['manual'])->toBe(449.0);
    expect($daily[26]['chapa'])->toBe(0.0);
    expect($daily[0]['total'])->toBe(0.0);
    expect($daily[29]['total'])->toBe(0.0);
});

it('zero-fills the monthly series', function () {
    revenueRow('manual', 'yearly', 3999, daysAgo: 2);

    $monthly = app(MarketplaceRevenueAnalyticsService::class)->monthly(12);

    expect($monthly)->toHaveCount(12);
    expect($monthly[11]['manual'])->toBe(3999.0);
    expect($monthly[0]['total'])->toBe(0.0);
});

it('groups revenue by plan', function () {
    revenueRow('chapa', 'weekly', 149);
    revenueRow('manual', 'yearly', 3999);

    $byPlan = app(MarketplaceRevenueAnalyticsService::class)->byPlan();

    expect($byPlan)->toHaveCount(1);
    expect($byPlan[0]['plan'])->toBe('standard');
    expect($byPlan[0]['total'])->toBe(4148.0);
    expect($byPlan[0]['count'])->toBe(2);
});

it('the revenue page is superadmin only', function () {
    [$payer] = revenueUser();

    $this->actingAs($payer)->get(route('admin.marketplace-revenue.index'))->assertForbidden();

    // actingAs persists for the rest of the test, so sign them out first.
    $this->app['auth']->forgetGuards();

    $this->get(route('admin.marketplace-revenue.index'))->assertRedirect(route('login'));
});

it('the revenue page renders the totals', function () {
    $admin = revenueAdmin();

    revenueRow('manual', 'yearly', 3999);

    $this->actingAs($admin)
        ->get(route('admin.marketplace-revenue.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/marketplace/revenue')
            ->where('summary.totals.total_etb', 3999)
            ->where('summary.totals.by_provider.manual', 3999)
            ->where('summary.totals.by_provider.chapa', 0)
        );
});

it('the revenue page carries no contact data', function () {
    $admin = revenueAdmin();

    [$payer, $workspace] = revenueUser();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-REVPENDING',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-SECRET',
        'manual_payer_phone' => '+251911999888',
        'manual_proof_path' => 'manual-payment-proofs/2026/10/secret.png',
        'open_manual_workspace_id' => $workspace->id,
    ]);

    $summary = json_encode(
        $this->actingAs($admin)
            ->get(route('admin.marketplace-revenue.index'))
            ->viewData('page')['props']['summary']
    );

    expect($summary)->not->toContain('FT-SECRET');
    expect($summary)->not->toContain('+251911999888');
    expect($summary)->not->toContain('manual-payment-proofs');
});

// ── M: billing history ────────────────────────────────────────────────────

it('billing history lists marketplace payments for both providers', function () {
    [$payer, $workspace] = revenueUser();
    revenueRow('chapa', 'monthly', 449, daysAgo: 40);
    revenueRow('manual', 'yearly', 3999, daysAgo: 2);

    // Re-point the second at the same workspace the pager will read.
    DB::table('marketplace_payment_transactions')
        ->where('workspace_id', '!=', $workspace->id)
        ->update(['workspace_id' => $workspace->id]);

    $this->actingAs($payer)
        ->get(route('billing.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('marketplacePayments', fn ($rows) => collect($rows)->count() === 2)
            ->where('marketplacePayments', fn ($rows) => collect($rows)->contains(
                fn (array $r) => $r['provider'] === 'manual' && $r['amount_etb'] === '3999.00'
            ))
            ->where('marketplacePayments', fn ($rows) => collect($rows)->contains(
                fn (array $r) => $r['provider'] === 'chapa' && $r['amount_etb'] === '449.00'
            ))
        );
});

it('billing history shows only verified payments', function () {
    [$payer, $workspace] = revenueUser();

    DB::table('marketplace_payment_transactions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-UNVERIFIED',
        'amount_etb' => 999,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-UNVER',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($payer)
        ->get(route('billing.history'))
        ->assertInertia(fn ($page) => $page
            ->where('marketplacePayments', fn ($rows) => count($rows) === 0)
        );
});

it('billing history shows another workspace nothing', function () {
    [$payer] = revenueUser();

    revenueRow('manual', 'yearly', 3999);

    $this->actingAs($payer)
        ->get(route('billing.history'))
        ->assertInertia(fn ($page) => $page
            ->where('marketplacePayments', fn ($rows) => count($rows) === 0)
        );
});

it('billing history never exposes the payer phone or proof path', function () {
    [$payer, $workspace] = revenueUser();

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $payer->id,
        'tx_ref' => 'NF-HISTORY',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => MarketplacePaymentTransaction::MANUAL_CONFIRMED,
        'mode' => 'manual',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-VISIBLE',
        'manual_payer_phone' => '+251911000999',
        'manual_proof_path' => 'manual-payment-proofs/2026/10/secret.png',
        'verified_at' => now()->subDay(),
    ]);

    $payload = json_encode(
        $this->actingAs($payer)->get(route('billing.history'))->viewData('page')['props']
    );

    expect($payload)->not->toContain('+251911000999');
    expect($payload)->not->toContain('manual-payment-proofs');

    // The bank reference IS shown: the payer typed it and needs to recognise it.
    expect($payload)->toContain('FT-VISIBLE');
});
