<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceReminderLog;
use App\Models\MarketplaceSubscription;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\MarketplacePlanReminder;
use App\Services\ReminderRecipients;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about a workspace's subscription, and what it says.
 *
 * Billing is a workspace's business and not one person's job, so this is
 * deliberately not "the owner". The permission check is the same
 * WorkspacePolicy::manageBilling the whole billing UI already uses, so there is
 * no second definition of who may act on billing here.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    Notification::fake();
});

/**
 * @return array{0: MarketplaceSubscription, 1: Workspace}
 */
function reminderFixture(float $days = 1, string $interval = 'monthly'): array
{
    $plan = MarketplacePlan::where('slug', 'standard')->firstOrFail();
    $workspace = Workspace::factory()->create();
    $endsAt = now()->addDays($days);

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $workspace->owner?->id,
        'status' => 'active',
        'billing_interval' => $interval,
        'current_period_start' => $endsAt->copy()->subDays(30),
        'current_period_ends_at' => $endsAt,
        'ends_at' => $endsAt,
        'chapa_tx_ref' => 'NF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
    ]);

    return [$subscription->fresh(['plan', 'workspace.owner']), $workspace];
}

/**
 * A member who can manage billing.
 */
function billingMember(Workspace $workspace, string $role = 'admin', bool $canManageBilling = false): User
{
    $user = User::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'account_type' => 'business',
        'display_name' => $user->name,
        'phone' => '+251900000000',
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $workspace->users()->attach($user->id, [
        'role' => $role,
        'permissions' => $canManageBilling ? json_encode(['manage_billing']) : null,
    ]);

    return $user->fresh();
}

// ── recipients ────────────────────────────────────────────────────────────

it('notifies the owner', function () {
    [$subscription] = reminderFixture();

    expect(ReminderRecipients::forWorkspace($subscription->workspace)->pluck('id')->all())
        ->toBe([$subscription->workspace->owner->id]);
});

it('does not notify an admin who has not been granted billing', function () {
    [$subscription] = reminderFixture();
    $admin = billingMember($subscription->workspace, 'admin');

    // An admin passes manage_team, manage_webhooks and view_activity_logs
    // implicitly, but NOT manage_billing. Being told about money is not part of
    // being an admin here, and the reminder follows the same rule as the button.
    expect(ReminderRecipients::forWorkspace($subscription->workspace)->pluck('id')->all())
        ->not->toContain($admin->id);
});

it('notifies a member who has explicitly been granted billing', function () {
    [$subscription] = reminderFixture();
    $treasurer = billingMember($subscription->workspace, 'member', canManageBilling: true);

    $ids = ReminderRecipients::forWorkspace($subscription->workspace)->pluck('id')->all();

    expect($ids)->toContain($subscription->workspace->owner->id)
        ->toContain($treasurer->id);
});

it('does not notify a plain member', function () {
    [$subscription] = reminderFixture();
    $member = billingMember($subscription->workspace, 'member');

    expect(ReminderRecipients::forWorkspace($subscription->workspace)->pluck('id')->all())
        ->not->toContain($member->id);
});

it('does not notify a viewer', function () {
    [$subscription] = reminderFixture();
    $viewer = billingMember($subscription->workspace, 'viewer');

    expect(ReminderRecipients::forWorkspace($subscription->workspace)->pluck('id')->all())
        ->not->toContain($viewer->id);
});

it('returns each person once, even with several of them able to pay', function () {
    [$subscription] = reminderFixture();
    billingMember($subscription->workspace, 'member', canManageBilling: true);
    billingMember($subscription->workspace, 'admin', canManageBilling: true);

    $ids = ReminderRecipients::forWorkspace($subscription->workspace)->pluck('id');

    // unique('id') rather than trusting the query. The pivot already prevents one
    // person holding two rows, but a recipients list built by any other route --
    // an owner who is also granted the permission explicitly, say -- would
    // otherwise deliver the same email twice.
    expect($ids->count())->toBe($ids->unique()->count())
        ->and($ids)->toContain($subscription->workspace->owner->id);
});

it('falls back to the owner when nobody holds the permission', function () {
    [$subscription] = reminderFixture();
    billingMember($subscription->workspace, 'member');
    billingMember($subscription->workspace, 'viewer');

    $ids = ReminderRecipients::forWorkspace($subscription->workspace)->pluck('id')->all();

    // A workspace with no recipient at all would lapse with nobody told, which is
    // the exact failure the reminders exist to prevent.
    expect($ids)->not->toBeEmpty()
        ->and($ids)->toBe([$subscription->workspace->owner->id]);
});

it('sends one notification per recipient, not one per role', function () {
    [$subscription] = reminderFixture();
    $treasurer = billingMember($subscription->workspace, 'member', canManageBilling: true);

    $log = MarketplaceReminderLog::create([
        'marketplace_subscription_id' => $subscription->id,
        'workspace_id' => $subscription->workspace_id,
        'reminder_type' => 'renewal_1d',
        'channel' => 'database',
        'status' => MarketplaceReminderLog::STATUS_CLAIMED,
    ]);

    app(App\Services\ReminderDispatcher::class)->dispatch($log);

    Notification::assertSentToTimes($treasurer, MarketplacePlanReminder::class, 1);
    Notification::assertSentToTimes($subscription->workspace->owner, MarketplacePlanReminder::class, 1);
});

// ── the payload carries no contact data ───────────────────────────────────

it('carries no payer phone, bank reference or proof path', function () {
    [$subscription] = reminderFixture();

    // The sensitive columns live on the payment transaction, which is where the
    // payer phone and bank reference actually are.
    App\Models\MarketplacePaymentTransaction::create([
        'workspace_id' => $subscription->workspace_id,
        'plan_id' => $subscription->plan_id,
        'user_id' => $subscription->user_id,
        'tx_ref' => 'NF-SENSITIVE',
        'status' => 'verified',
        'currency' => 'ETB',
        'amount_etb' => 449,
        'billing_interval' => 'monthly',
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_payer_phone' => '+251911223344',
        'manual_reference' => 'FT123456789',
        'manual_proof_path' => 'manual-payment-proofs/2026/10/abc.png',
    ]);

    $notification = new MarketplacePlanReminder($subscription->fresh(['plan', 'workspace.owner']), 'renewal_1d');
    $payload = json_encode($notification->toArray($subscription->workspace->owner))
        .json_encode($notification->toMail($subscription->workspace->owner)->toArray());

    expect($payload)
        ->not->toContain('+251911223344')
        ->not->toContain('FT123456789')
        ->not->toContain('manual-payment-proofs')
        ->not->toContain('payer_phone')
        ->not->toContain('proof_path');
});

it('names the plan, the duration and the end date', function () {
    [$subscription] = reminderFixture(1, 'monthly');

    $message = (new MarketplacePlanReminder($subscription, 'renewal_1d'))
        ->toArray($subscription->workspace->owner)['message'];

    expect($message)->toContain('Standard')
        ->toContain('monthly')
        ->toContain(now()->addDay()->format('j M Y'));
});

it('quotes the price as it is now, not what the last holder paid', function () {
    [$subscription] = reminderFixture(1, 'monthly');

    $message = (new MarketplacePlanReminder($subscription, 'renewal_1d'))
        ->toArray($subscription->workspace->owner)['message'];

    expect($message)->toContain('449');

    // The price is read at send time. Quoting what the last holder paid would be
    // actively wrong if it has changed since they renewed.
    $subscription->plan->update(['price_monthly_etb' => 500]);

    $updated = (new MarketplacePlanReminder($subscription->fresh(['plan', 'workspace.owner']), 'renewal_1d'))
        ->toArray($subscription->workspace->owner);

    expect($updated['message'])->toContain('500');
});

it('links to the plans page', function () {
    [$subscription] = reminderFixture();
    $owner = $subscription->workspace->owner;

    $mail = (new MarketplacePlanReminder($subscription, 'renewal_1d'))->toMail($owner);

    expect($mail->actionUrl)->toBe(route('billing.plans'));
});

it('uses a translated subject and body per locale', function () {
    [$subscription] = reminderFixture();

    foreach (['en', 'fr', 'es', 'ar'] as $locale) {
        app()->setLocale($locale);

        $notification = new MarketplacePlanReminder($subscription, 'renewal_1d');

        expect($notification->toArray($subscription->workspace->owner)['title'])->not->toBe('')
            ->and($notification->toArray($subscription->workspace->owner)['message'])->not->toBe('');
    }

    // Back to English so it cannot leak into another test.
    app()->setLocale('en');
});

it('does not fall back to an English string when a locale is missing', function () {
    [$subscription] = reminderFixture();

    app()->setLocale('de');

    $title = (new MarketplacePlanReminder($subscription, 'renewal_1d'))
        ->toArray($subscription->workspace->owner)['title'];

    // No German file exists, so Laravel returns the key itself rather than an
    // English sentence. That is visible as a key and therefore obvious in a
    // screenshot, which is the good failure.
    expect($title)->toBe('marketplace.reminder.subject.renewal_1d');
});