<?php

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Notifications\ManualPaymentExpired;
use App\Services\ManualPaymentExpiryService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Expiry closes a request and grants nothing.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/**
 * An open manual request, optionally already past its window.
 *
 * @return array{0: User, 1: MarketplacePaymentTransaction}
 */
function expiryFixture(int $submittedDaysAgo = 0, int $expiresInDays = 14): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    $transaction = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-EXPIRY'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
        'mode' => 'test',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT'.random_int(100000, 999999),
        'manual_payer_phone' => '+251911000111',
        'manual_submitted_at' => now()->subDays($submittedDaysAgo),
        'manual_expires_at' => now()->addDays($expiresInDays),
        'open_manual_workspace_id' => $workspace->id,
    ]);

    return [$user->fresh(), $transaction];
}

// ── the setting ───────────────────────────────────────────────────────────

it('defaults the review window to fourteen days', function () {
    expect(app(ManualPaymentExpiryService::class)->windowDays())->toBe(14);
    expect(config('marketplace.free_manual_payment_expiry_days'))->toBe(14);
});

it('seeds the setting row so an admin can change it', function () {
    expect(DB::table('marketplace_settings')->where('key', 'free_manual_payment_expiry_days')->value('value'))
        ->toBe(14);
});

it('honours an admin change to the window', function () {
    DB::table('marketplace_settings')
        ->where('key', 'free_manual_payment_expiry_days')
        ->update(['value' => 30]);

    expect(app(ManualPaymentExpiryService::class)->windowDays())->toBe(30);
});

it('falls back to fourteen if the setting is nonsense', function () {
    DB::table('marketplace_settings')
        ->where('key', 'free_manual_payment_expiry_days')
        ->update(['value' => 0]);

    expect(app(ManualPaymentExpiryService::class)->windowDays())->toBe(14);
});

// ── the transition ────────────────────────────────────────────────────────

it('lapses a request past its window', function () {
    [$user, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $result = app(ManualPaymentExpiryService::class)->run();

    expect($result['expired'])->toBe(1);

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(MarketplacePaymentTransaction::MANUAL_EXPIRED);
    expect($fresh->open_manual_workspace_id)->toBeNull();
});

it('leaves a request inside its window alone', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 2, expiresInDays: 12);

    expect(app(ManualPaymentExpiryService::class)->run()['expired'])->toBe(0);

    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_OPEN_STATUS);
    expect($transaction->fresh()->open_manual_workspace_id)->not->toBeNull();
});

it('lapses a request exactly on its expiry second', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 14, expiresInDays: 0);

    $this->travel(1)->second();

    expect(app(ManualPaymentExpiryService::class)->run()['expired'])->toBe(1);
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_EXPIRED);
});

it('grants nothing', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    app(ManualPaymentExpiryService::class)->run();

    expect(MarketplaceSubscription::count())->toBe(0);
    expect(MarketplaceSubscription::where('chapa_tx_ref', $transaction->tx_ref)->count())->toBe(0);
    expect($transaction->fresh()->verified_at)->toBeNull();
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_EXPIRED);
});

it('creates no receipt', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    app(ManualPaymentExpiryService::class)->run();

    expect(MarketplacePaymentReceipt::where('transaction_id', $transaction->id)->count())->toBe(0);
});

it('notifies the payer', function () {
    Notification::fake();

    [$user, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    app(ManualPaymentExpiryService::class)->run();

    Notification::assertSentTo($user, ManualPaymentExpired::class, function (ManualPaymentExpired $notification) use ($transaction) {
        return $notification->transaction->id === $transaction->id;
    });
});

it('the expiry notification carries no contact data', function () {
    [$user, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $notification = new ManualPaymentExpired($transaction);

    $payload = json_encode([
        'database' => $notification->toArray($user),
        'mail' => (string) $notification->toMail($user)->render(),
    ]);

    expect($payload)->not->toContain('+251911000111');
    expect($payload)->not->toContain('payer_phone');
    expect($payload)->not->toContain('bank');
});

it('frees the workspace slot so a new request can be made', function () {
    [$user, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    expect($transaction->open_manual_workspace_id)->not->toBeNull();

    app(ManualPaymentExpiryService::class)->run();

    expect($transaction->fresh()->open_manual_workspace_id)->toBeNull();

    // A new request for the same workspace now fits.
    MarketplacePaymentTransaction::create([
        'workspace_id' => $transaction->workspace_id,
        'plan_id' => $transaction->plan_id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-AFTER-EXPIRY',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'mode' => 'test',
        'provider' => 'manual',
        'status' => MarketplacePaymentTransaction::MANUAL_OPEN_STATUS,
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-NEW',
        'open_manual_workspace_id' => $transaction->workspace_id,
    ]);

    expect(MarketplacePaymentTransaction::where('status', MarketplacePaymentTransaction::MANUAL_OPEN_STATUS)->count())->toBe(1);
});

// ── D: only open rows may lapse ───────────────────────────────────────────

it('will not lapse a confirmed row', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $transaction->update([
        'status' => MarketplacePaymentTransaction::MANUAL_CONFIRMED,
        'verified_at' => now()->subDay(),
        'open_manual_workspace_id' => null,
    ]);

    expect(app(ManualPaymentExpiryService::class)->run()['expired'])->toBe(0);
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_CONFIRMED);
});

it('will not lapse a rejected row', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $transaction->update([
        'status' => MarketplacePaymentTransaction::MANUAL_REJECTED,
        'open_manual_workspace_id' => null,
    ]);

    expect(app(ManualPaymentExpiryService::class)->run()['expired'])->toBe(0);
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_REJECTED);
});

it('will not lapse an already expired row twice', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    Notification::fake();

    expect(app(ManualPaymentExpiryService::class)->run()['expired'])->toBe(1);
    expect(app(ManualPaymentExpiryService::class)->run()['expired'])->toBe(0);

    // One notification, not two.
    Notification::assertSentTimes(ManualPaymentExpired::class, 1);
});

it('expire() reports false when the row is already taken', function () {
    [, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $transaction->update([
        'status' => MarketplacePaymentTransaction::MANUAL_CONFIRMED,
        'open_manual_workspace_id' => null,
    ]);

    expect(app(ManualPaymentExpiryService::class)->expire($transaction))->toBeFalse();
});

it('confirm racing expire resolves to exactly one outcome', function () {
    // Run sequentially on purpose: the suite is on SQLite, where lockForUpdate is
    // a no-op, so this cannot test real concurrency. What it tests is that each
    // transition requires an open row, so whichever runs first closes it and the
    // second finds nothing to do.
    [$user, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $expiry = app(ManualPaymentExpiryService::class);

    // Expiry first.
    expect($expiry->expire($transaction))->toBeTrue();
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_EXPIRED);

    // A confirm arriving afterwards must not walk it back or grant anything.
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_EXPIRED);
    expect(MarketplaceSubscription::count())->toBe(0);
    expect($expiry->expire($transaction))->toBeFalse();
});

it('ignores Chapa rows entirely', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-CHAPA-OLD',
        'amount_etb' => 149,
        'currency' => 'ETB',
        'billing_interval' => 'weekly',
        'status' => 'pending',
        'mode' => 'test',
        'provider' => 'chapa',
        'created_at' => now()->subDays(60),
        'updated_at' => now()->subDays(60),
    ]);

    expect(app(ManualPaymentExpiryService::class)->run()['expired'])->toBe(0);
});

// ── the command ───────────────────────────────────────────────────────────

it('dry run reports without writing', function () {
    Notification::fake();

    [, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $exit = Artisan::call('marketplace:expire-manual-payments', ['--dry-run' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect($output)->toContain('Dry run');
    expect($output)->toContain($transaction->tx_ref);

    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_OPEN_STATUS);

    Notification::assertNothingSent();
});

it('the real run lapses and notifies', function () {
    Notification::fake();

    [$user, $transaction] = expiryFixture(submittedDaysAgo: 20, expiresInDays: -1);

    $exit = Artisan::call('marketplace:expire-manual-payments');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('lapsed');
    expect($transaction->fresh()->status)->toBe(MarketplacePaymentTransaction::MANUAL_EXPIRED);

    Notification::assertSentTo($user, ManualPaymentExpired::class);
});

it('says so plainly when there is nothing to do', function () {
    Artisan::call('marketplace:expire-manual-payments');

    expect(Artisan::output())->toContain('No manual payment requests are past their review window');
});

it('the command reports the window it used', function () {
    DB::table('marketplace_settings')
        ->where('key', 'free_manual_payment_expiry_days')
        ->update(['value' => 21]);

    Artisan::call('marketplace:expire-manual-payments', ['--dry-run' => true]);

    expect(Artisan::output())->toContain('21 day(s)');
});

// ── E: the disabled-by-default flag ───────────────────────────────────────

it('is not scheduled unless the flag is on', function () {
    // With the flag off -- the default -- the scheduler must not run it.
    expect(config('marketplace.expiry_enabled'))->toBeFalse();
});

it('expires a request whose window has run out', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);

    $service = app(ManualPaymentExpiryService::class);

    // What submit() would have stamped.
    $expiresAt = $service->expiryFromNow();

    expect($expiresAt->toDateString())->toBe(now()->addDays(14)->toDateString());
    expect($expiresAt->isFuture())->toBeTrue();
});
