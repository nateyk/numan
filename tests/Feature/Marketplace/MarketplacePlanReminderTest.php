<?php

use App\Models\CargoType;
use App\Models\FreightPost;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use App\Models\MarketplaceSubscription;
use App\Models\TruckType;
use App\Models\Workspace;
use App\Notifications\MarketplacePlanReminder;
use App\Services\MarketplaceListingVisibilityService;
use App\Services\ReminderTimezone;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The expiry copy, and how dates are rendered.
 *
 * The expiry reminder says listings "may be hidden". That has to be true when it
 * says it and untrue when it stays quiet, because the My Freight banner says the
 * same thing with a real number. Two places disagreeing about the same fact is
 * worse than either being silent.
 *
 * The date is rendered in the reader's timezone, because "ends 02:14" in UTC is
 * correct and useless.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);

    // freight_posts has foreign keys onto both, and the plan seeder does not
    // create reference data.
    $cargo = CargoType::factory()->create();
    $truck = TruckType::factory()->create();

    config([
        'marketplace.reminder_fallback_timezone' => 'Africa/Addis_Ababa',
        'test.cargo_type_id' => $cargo->id,
        'test.truck_type_id' => $truck->id,
    ]);

    // The over-limit fixtures below are written against a free limit of one
    // live post. Pin it explicitly so they do not move when the global Free
    // default changes.
    MarketplaceSetting::updateOrCreate(['key' => 'free_active_posts'], ['value' => 1]);
});

/**
 * @return array{0: MarketplaceSubscription, 1: Workspace}
 */
function expiredFixture(int $postsOverLimit = 0): array
{
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();
    $owner = $workspace->owner;

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $owner?->id,
        'status' => 'expired',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(40),
        'current_period_ends_at' => now()->subDays(10),
        'ends_at' => now()->subDays(1),
        'chapa_tx_ref' => 'NF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
    ]);

    // The Free limit is one live post by default.
    for ($i = 0; $i < $postsOverLimit; $i++) {
        FreightPost::create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $owner?->id,
            'workspace_id' => $workspace->id,
            'status' => 'active',
            'cargo_type_id' => config('test.cargo_type_id'),
            'item_name' => 'Cement',
            'actual_weight' => 20,
            'weight_unit' => 'ton',
            'tariff_amount' => 15000,
            'tariff_currency' => 'ETB',
            'truck_count' => 2,
            'required_truck_type_id' => config('test.truck_type_id'),
            'schedule_type' => 'one_time',
            'pickup_date' => now()->addDays(5)->toDateString(),
        ]);
    }

    return [$subscription->fresh(['plan', 'workspace.owner']), $workspace];
}

/**
 * The rendered mail body as plain text.
 */
function mailBody(MarketplaceSubscription $subscription): string
{
    $mail = (new MarketplacePlanReminder($subscription, 'plan_expired'))->toMail($subscription->workspace->owner);

    return implode("\n", array_merge([$mail->subject], $mail->introLines, $mail->outroLines));
}

// ── E: the hidden-listings sentence ───────────────────────────────────────

it('says listings may be hidden when they would be', function () {
    // Four posts against a free limit of one.
    [$subscription] = expiredFixture(4);

    expect(mailBody($subscription))->toContain(__('marketplace.reminder.hidden_note'));
});

it('omits the sentence when nothing would be hidden', function () {
    // No posts at all, so nothing is over the limit. Saying "your listings may be
    // hidden" here would contradict a banner that is not showing.
    [$subscription] = expiredFixture(0);

    expect(mailBody($subscription))->not->toContain(__('marketplace.reminder.hidden_note'));
});

it('omits the sentence when the workspace is exactly at the limit', function () {
    [$subscription] = expiredFixture(1);

    expect(mailBody($subscription))->not->toContain(__('marketplace.reminder.hidden_note'));
});

it('counts against the limit an admin has set, not the default', function () {
    MarketplaceSetting::where('key', 'free_active_posts')->update(['value' => 10]);

    [$subscription] = expiredFixture(4);

    // Four posts, limit now ten. Quiet, because the banner is not showing.
    expect(mailBody($subscription))->not->toContain(__('marketplace.reminder.hidden_note'));
});

it('agrees with the banner about a workspace over its limit', function () {
    [$subscription] = expiredFixture(4);

    // hideExcessPosts() is what sets hidden_at, which is what the banner counts.
    // expireLapsedSubscriptions() only flips the status column.
    app(MarketplaceListingVisibilityService::class)->hideExcessPosts();

    $bannerCount = FreightPost::whereNotNull('hidden_at')->count();

    // The banner is driven by hidden_at, which only the sweep sets. The reminder
    // computes the same overage from livePostCount. Both must agree, or the
    // owner gets "3 posts are hidden" on the banner and "may be hidden" in an
    // email that is right for a different reason.
    expect($bannerCount)->toBeGreaterThan(0)
        ->and(mailBody($subscription))->toContain(__('marketplace.reminder.hidden_note'));
});

it('says nothing about listings on a renewal reminder', function () {
    // Nothing is being hidden yet, so the sentence would be noise on every
    // renewal email.
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();
    $ends = now()->addDay();

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $workspace->owner?->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => $ends->copy()->subDays(29),
        'current_period_ends_at' => $ends,
        'ends_at' => $ends,
        'chapa_tx_ref' => 'NF-RENEW',
    ])->fresh(['plan', 'workspace.owner']);

    expect(mailBody($subscription))->not->toContain(__('marketplace.reminder.hidden_note'));
});

it('points at the plans page, since that is what fixes it', function () {
    [$subscription] = expiredFixture(4);

    expect((new MarketplacePlanReminder($subscription, 'plan_expired'))
        ->toMail($subscription->workspace->owner)->actionUrl)
        ->toBe(route('billing.plans'));
});

// ── F: dates in the reader's timezone ─────────────────────────────────────

it('renders the date in the recipient timezone', function () {
    $when = now()->setTimezone('UTC')->startOfDay();

    expect(ReminderTimezone::format($when, 'Africa/Addis_Ababa'))
        ->toBe($when->clone()->setTimezone('Africa/Addis_Ababa')->format('j M Y'));
});

it('falls back to Addis Ababa when the recipient has no timezone', function () {
    $when = now()->setTimezone('UTC');

    // The business is Ethiopian. Falling back to the server's UTC would put the
    // "expires tomorrow" email out at an hour nobody reads it.
    expect(ReminderTimezone::format($when, null))
        ->toBe($when->clone()->setTimezone('Africa/Addis_Ababa')->format('j M Y'))
        ->and(ReminderTimezone::for(null)->getName())->toBe('Africa/Addis_Ababa');
});

it('falls back when the stored timezone is nonsense', function () {
    $when = now();

    // Checked rather than caught: an invalid zone throws, and throwing once per
    // recipient in a loop over a workspace is a bad way to discover somebody set
    // theirs to "Africa/Addis".
    expect(ReminderTimezone::for('Africa/Addis')->getName())->toBe('Africa/Addis_Ababa')
        ->and(ReminderTimezone::format($when, 'not a timezone'))
        ->toBe($when->clone()->setTimezone('Africa/Addis_Ababa')->format('j M Y'));
});

it('renders a date that differs across two timezones', function () {
    // 23:30 UTC on the 5th is already the 6th in Addis, which is +3. A reminder
    // saying "ends 5 Oct" would be a day out for the reader.
    $when = Carbon::parse('2026-10-05 23:30:00', 'UTC');

    expect(ReminderTimezone::format($when, 'UTC'))->toBe('5 Oct 2026')
        ->and(ReminderTimezone::format($when, 'Africa/Addis_Ababa'))->toBe('6 Oct 2026');
});

it('uses the recipient timezone in the rendered message', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();
    $owner = $workspace->owner;
    $endsAt = Carbon::parse('2026-10-05 23:30:00', 'UTC');

    // Set before the subscription is refreshed, because the refresh eagerly
    // loads workspace.owner and a stale cached owner would still be UTC.
    $owner->forceFill(['timezone' => 'Africa/Addis_Ababa'])->save();

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $owner?->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => $endsAt->copy()->subDays(29),
        'current_period_ends_at' => $endsAt,
        'ends_at' => $endsAt,
        'chapa_tx_ref' => 'NF-TZ',
    ])->fresh(['plan', 'workspace.owner']);

    $message = (new MarketplacePlanReminder($subscription, 'renewal_1d'))
        ->toArray($subscription->workspace->owner)['message'];

    expect($message)->toContain('6 Oct 2026');
});
it('says listings may be hidden before the expiry sweep has run', function () {
    // The other ordering. Nothing carries hidden_at yet, the posts are simply
    // over the limit, and the reminder must still mention it -- otherwise the
    // first reminder after a lapse would be silent about the thing the sweep is
    // about to do.
    [$subscription] = expiredFixture(4);

    expect(FreightPost::whereNotNull('hidden_at')->count())->toBe(0)
        ->and(mailBody($subscription))->toContain(__('marketplace.reminder.hidden_note'));
});

it('still mentions them once the sweep has hidden them', function () {
    // After the sweep the same posts leave livePostCount, so counting only the
    // overage would report zero while the banner is showing three.
    [$subscription] = expiredFixture(4);

    app(MarketplaceListingVisibilityService::class)->hideExcessPosts();

    expect(FreightPost::whereNotNull('hidden_at')->count())->toBe(3)
        ->and(mailBody($subscription))->toContain(__('marketplace.reminder.hidden_note'));
});
