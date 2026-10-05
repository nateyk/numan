<?php

use App\Models\FreightPost;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A fresh migrate plus marketplace:install yields a working marketplace.
 *
 * This is the deployment path, and it had a hole in it. migrate does not create
 * the standard plan, and the only seeder that does is reachable solely through
 * DatabaseSeeder, which builds a demo dataset that must never run against
 * production. So a correctly deployed production database had no active plan, the
 * plans page rendered empty, and nothing anywhere errored.
 */
uses(RefreshDatabase::class);

it('migrate alone leaves the marketplace with no active plan', function () {
    // The gap this command exists to close, asserted so it cannot silently
    // reappear: if a future migration starts creating the plan, this fails and
    // the command becomes redundant rather than mysteriously necessary.
    expect(MarketplacePlan::query()->where('is_active', true)->count())->toBe(0);
});

it('installs the standard plan', function () {
    $this->artisan('marketplace:install')->assertSuccessful();

    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();

    expect($plan->is_active)->toBeTrue()
        ->and((float) $plan->price_weekly_etb)->toBe(149.0)
        ->and((float) $plan->price_monthly_etb)->toBe(449.0)
        ->and((float) $plan->price_yearly_etb)->toBe(3999.0);
});

it('gives the plans page something to render', function () {
    $this->artisan('marketplace:install')->assertSuccessful();

    // A bare user gets 403 from the billing middleware; the page needs a
    // workspace, which is the normal case.
    [$user] = marketplaceTestUserWithWorkspace();

    $this->actingAs($user)
        ->get('/billing/plans')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/plans')
            // marketplacePlans, not plans: 'plans' is the Stripe array and is
            // legitimately empty.
            ->where('marketplacePlans', fn ($plans): bool => count($plans) >= 1)
            ->where('marketplacePlans.0.price.monthly', fn ($price): bool => (float) $price > 0)
        );
});

it('creates no demo data', function () {
    $before = [
        'users' => User::count(),
        'workspaces' => Workspace::count(),
        'freight' => FreightPost::count(),
    ];

    $this->artisan('marketplace:install')->assertSuccessful();

    // The whole reason this is a separate command rather than a seeder.
    expect(User::count())->toBe($before['users'])
        ->and(Workspace::count())->toBe($before['workspaces'])
        ->and(FreightPost::count())->toBe($before['freight']);
});

it('is idempotent', function () {
    $this->artisan('marketplace:install')->assertSuccessful();
    $this->artisan('marketplace:install')->assertSuccessful();

    expect(MarketplacePlan::where('slug', 'standard')->count())->toBe(1);
});

it('never overwrites a price an admin has set', function () {
    $this->artisan('marketplace:install')->assertSuccessful();

    MarketplacePlan::where('slug', 'standard')->update(['price_monthly_etb' => 999]);

    $this->artisan('marketplace:install')->assertSuccessful();

    // The whole reason it is firstOrCreate and not updateOrCreate.
    expect((float) MarketplacePlan::where('slug', 'standard')->firstOrFail()->price_monthly_etb)->toBe(999.0);
});

it('never overwrites a limit an admin has edited', function () {
    // The row already exists -- its migration seeds it -- so this is an admin
    // edit rather than an insert.
    MarketplaceSetting::where('key', 'free_active_posts')->update(['value' => 42]);

    $this->artisan('marketplace:install')->assertSuccessful();

    expect((int) MarketplaceSetting::where('key', 'free_active_posts')->firstOrFail()->value)->toBe(42);
});

it('tops up a missing free setting', function () {
    MarketplaceSetting::where('key', 'free_contact_reveals_per_week')->delete();

    $this->artisan('marketplace:install')->assertSuccessful();

    expect(MarketplaceSetting::where('key', 'free_contact_reveals_per_week')->exists())->toBeTrue();
});

it('writes nothing on a dry run', function () {
    $this->artisan('marketplace:install --dry-run')
        ->expectsOutputToContain('standard would be created')
        ->assertFailed();

    expect(MarketplacePlan::where('slug', 'standard')->exists())->toBeFalse()
        ->and(MarketplacePlan::query()->where('is_active', true)->count())->toBe(0);
});

it('fails loudly when there is still no active plan', function () {
    // Exits non-zero rather than reporting success, so a deploy script notices.
    // The dry run above relies on this.
    $this->artisan('marketplace:install --dry-run')->assertFailed();
});

it('leaves an archived plan archived', function () {
    $plan = MarketplacePlan::create([
        'slug' => 'standard',
        'name' => 'Standard',
        'price_weekly_etb' => 149,
        'price_monthly_etb' => 449,
        'price_yearly_etb' => 3999,
        'is_active' => false,
    ]);

    $this->artisan('marketplace:install')->assertFailed();

    // Un-archiving is a decision for a person. An installer that flipped it would
    // silently re-open sales at whatever prices are in the row.
    expect($plan->fresh()->is_active)->toBeFalse();
});

it('reports which payment instruction keys are missing', function () {
    DB::table('marketplace_text_settings')->where('key', 'manual_payment_telebirr_number')->delete();

    $this->artisan('marketplace:install')
        ->expectsOutputToContain('manual_payment_telebirr_number')
        ->assertSuccessful();
});
