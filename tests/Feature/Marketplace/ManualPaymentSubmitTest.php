<?php

use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ManualPaymentSubmitted;
use App\Services\ManualPaymentService;
use App\Services\ManualPaymentTokenService;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceTextSettingsService;
use App\Services\PhoneNumberService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Submitting a manual payment request.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);

    Notification::fake();

    app(MarketplaceTextSettingsService::class)->setMany([
        'manual_payment_telebirr_number' => ['value' => '1000234567894', 'is_enabled' => true],
        'manual_payment_telebirr_name' => ['value' => 'XCO Agency', 'is_enabled' => true],
    ]);
});

/**
 * A billing member with a phone, a workspace and an onboarded profile.
 *
 * @return array{0: User, 1: Workspace}
 */
function submitUser(): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Payer',
        'phone' => '+251911000111',
    ]);

    return [$user->fresh(), $workspace];
}

/**
 * @return array{0: User, 1: Workspace, 2: string, 3: array<string, mixed>}
 */
function submitReady(string $interval = 'monthly'): array
{
    [$user, $workspace] = submitUser();

    $issued = app(ManualPaymentService::class)->issueInstructions($user, 'standard', $interval);

    return [$user, $workspace, $issued['token'], $issued['terms']];
}

/**
 * A valid submit payload, with overrides.
 *
 * @return array<string, mixed>
 */
function submitPayload(array $overrides = []): array
{
    return array_merge([
        'method' => 'telebirr',
        'reference' => 'FT123456789',
        'payer_phone' => '0911223344',
    ], $overrides);
}

// ── the instructions page ─────────────────────────────────────────────────

it('issues a token and writes no row', function () {
    [$user, $workspace, $token, $terms] = submitReady();

    expect($token)->not->toBeEmpty();
    expect($terms['tx_ref'])->toStartWith('NF-');

    // The whole point: nothing exists until submit.
    expect(MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('the instructions page shows the amount and the NF code', function () {
    [$user] = submitReady('yearly');

    $response = $this->actingAs($user)
        ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'yearly']));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('marketplace/manual-pay')
        ->where('amountEtb', '3999.00')
        ->where('billingInterval', 'yearly')
        ->where('txRef', fn (string $ref) => str_starts_with($ref, 'NF-'))
        ->where('methods.telebirr.fields.manual_payment_telebirr_number', '1000234567894')
    );
});

it('refuses the instructions page when nothing is configured', function () {
    DB::table('marketplace_text_settings')->update(['is_enabled' => false, 'value' => null]);

    [$user] = submitReady();

    $this->actingAs($user)
        ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'monthly']))
        ->assertRedirect()
        ->assertSessionHasErrors('manual_payment');
});

it('refuses the instructions page for a zero-priced duration', function () {
    MarketplacePlan::where('slug', 'standard')->update(['price_weekly_etb' => 0]);

    [$user] = submitReady();

    $this->actingAs($user)
        ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'weekly']))
        ->assertRedirect()
        ->assertSessionHasErrors('plan');
});

// ── H: only billing members may submit ────────────────────────────────────

it('refuses a workspace member who cannot manage billing', function () {
    [$owner, $workspace] = submitUser();

    $issued = app(ManualPaymentService::class)->issueInstructions($owner, 'standard', 'monthly');

    $member = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace->users()->attach($member, ['role' => 'member']);

    Profile::firstOrCreate(['user_id' => $member->id], [
        'onboarding_step' => 'completed',
        'phone' => '+251911000222',
    ]);

    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($member->fresh())
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $issued['token']]))
        ->assertForbidden();

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('refuses a guest', function () {
    $this->post(route('marketplace.manual.store'), submitPayload())
        ->assertRedirect(route('login'));
});

it('refuses the instructions page to a member who cannot manage billing', function () {
    [$owner, $workspace] = submitUser();

    $member = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace->users()->attach($member, ['role' => 'member']);
    Profile::firstOrCreate(['user_id' => $member->id], ['onboarding_step' => 'completed']);
    $member->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($member->fresh())
        ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'monthly']))
        ->assertForbidden();
});

// ── the happy path ────────────────────────────────────────────────────────

it('records the agreed terms from the token', function () {
    [$user, $workspace, $token, $terms] = submitReady('yearly');

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $token]))
        ->assertRedirect();

    $transaction = MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->firstOrFail();

    expect($transaction->provider)->toBe('manual');
    expect($transaction->status)->toBe('pending_confirmation');
    expect($transaction->tx_ref)->toBe($terms['tx_ref']);
    expect((float) $transaction->amount_etb)->toBe((float) $terms['amount_etb']);
    expect($transaction->billing_interval)->toBe('yearly');
    expect($transaction->plan_id)->toBe($terms['plan_id']);
    expect($transaction->currency)->toBe('ETB');
});

it('grants nothing while pending', function () {
    [$user, $workspace, $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    expect(MarketplaceSubscription::where('workspace_id', $workspace->id)->count())->toBe(0);
    expect(MarketplacePaymentReceipt::count())->toBe(0);

    // And the entitlements are unchanged.
    $entitlements = app(MarketplaceEntitlementService::class);
    expect($entitlements->hasPaidSubscription($workspace))->toBeFalse();
    expect($entitlements->getLimitValue($workspace, 'contact_reveals_per_week'))->toBe(3);
});

it('normalises the payer phone and the reference', function () {
    [$user, $workspace, $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'reference' => '  ft123456789  ',
            'payer_phone' => '091 122 3344',
        ]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    expect($transaction->manual_reference)->toBe('FT123456789');
    expect($transaction->manual_payer_phone)->toBe('+251911223344');
});

it('refuses an invalid payer phone', function () {
    [$user, $workspace, $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'payer_phone' => '0211234567',
        ]))
        ->assertSessionHasErrors('payer_phone');

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('stamps the submission and expiry times', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    expect($transaction->manual_submitted_at)->not->toBeNull();
    expect($transaction->manual_expires_at->toDateString())
        ->toBe(now()->addDays(app(ManualPaymentService::class)->expiryDays())->toDateString());
    expect($transaction->manual_reviewed_at)->toBeNull();
    expect($transaction->manual_reviewed_by)->toBeNull();
});

// ── C: the frozen amount ──────────────────────────────────────────────────

it('freezes the amount shown, not the price at submit time', function () {
    [$user, $workspace, $token, $terms] = submitReady();

    $shown = (float) $terms['amount_etb'];

    // The admin raises the price after the instructions page was rendered.
    MarketplacePlan::where('slug', 'standard')->update(['price_monthly_etb' => 9999]);

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    expect((float) $transaction->amount_etb)->toBe($shown);
    expect((float) $transaction->amount_etb)->not->toBe(9999.0);
});

it('refuses a tampered token', function () {
    [$user] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => 'forged-token']))
        ->assertSessionHasErrors('token');

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('refuses an expired token', function () {
    [$user, , $token] = submitReady();

    $this->travel(ManualPaymentTokenService::TTL_HOURS + 1)->hours();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $token]))
        ->assertSessionHasErrors('token');

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('refuses a token whose method has since been switched off', function () {
    [$user, , $token] = submitReady();

    app(MarketplaceTextSettingsService::class)->setMany([
        'manual_payment_telebirr_number' => ['is_enabled' => false],
    ]);

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $token]))
        ->assertSessionHasErrors('method');

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

// ── B + A: the two constraints ────────────────────────────────────────────

it('collapses a duplicate reference however it was typed', function () {
    [$user] = submitReady();
    [$other] = submitUser();

    $first = app(ManualPaymentService::class)->issueInstructions($user, 'standard', 'monthly');
    $second = app(ManualPaymentService::class)->issueInstructions($other, 'standard', 'monthly');

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $first['token'],
            'reference' => 'abc12345',
        ]))
        ->assertRedirect();

    expect(MarketplacePaymentTransaction::first()->manual_reference)->toBe('ABC12345');

    // The same transfer, differently typed, by a different workspace.
    $this->actingAs($other)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $second['token'],
            'reference' => ' ABC12345 ',
        ]))
        ->assertSessionHasErrors('manual_payment');

    expect(MarketplacePaymentTransaction::count())->toBe(1);
});

it('refuses a second open request from the same workspace', function () {
    [$user, $workspace] = submitUser();

    $first = app(ManualPaymentService::class)->issueInstructions($user, 'standard', 'monthly');
    $second = app(ManualPaymentService::class)->issueInstructions($user, 'standard', 'yearly');

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $first['token'],
            'reference' => 'FT-ONE',
        ]))
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $second['token'],
            'reference' => 'FT-TWO',
        ]))
        ->assertSessionHasErrors('manual_payment');

    expect(MarketplacePaymentTransaction::count())->toBe(1);
    expect(MarketplacePaymentTransaction::first()->open_manual_workspace_id)->toBe($workspace->id);
});

it('will not even mint a second token while a request is open', function () {
    // The guard is the reachable behaviour: the instructions page refuses
    // outright rather than letting the payer fill in a form that cannot
    // succeed. The database index behind it is what holds under concurrency,
    // and that is proved by inserting directly in
    // ManualPaymentFoundationTest.
    [$user] = submitUser();

    $first = app(ManualPaymentService::class)->issueInstructions($user, 'standard', 'monthly');

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $first['token'],
            'reference' => 'FT-AAA',
        ]))
        ->assertRedirect();

    // A second instructions page for the same workspace is refused.
    $this->actingAs($user)
        ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'yearly']))
        ->assertRedirect()
        ->assertSessionHasErrors('manual_payment');
});

it('lets a second token be minted once the first request is closed', function () {
    [$user] = submitUser();

    $first = app(ManualPaymentService::class)->issueInstructions($user, 'standard', 'monthly');

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $first['token'],
            'reference' => 'FT-AAA',
        ]));

    // Close the first request the way an admin rejection would.
    MarketplacePaymentTransaction::query()->update([
        'status' => MarketplacePaymentTransaction::MANUAL_REJECTED,
        'open_manual_workspace_id' => null,
    ]);

    $second = app(ManualPaymentService::class)->issueInstructions(
        $user->fresh(),
        'standard',
        'yearly',
    );

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $second['token'],
            'reference' => 'FT-BBB',
        ]))
        ->assertRedirect();

    expect(MarketplacePaymentTransaction::count())->toBe(2);
});

it('claims the open slot on the row', function () {
    [$user, $workspace, $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    expect(MarketplacePaymentTransaction::first()->open_manual_workspace_id)->toBe($workspace->id);
});

// ── G: the proof file ─────────────────────────────────────────────────────

it('stores a proof on the private disk under a random name', function () {
    [$user, , $token] = submitReady();

    $file = UploadedFile::fake()->image('my-bank-screenshot.png', 400, 300);

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'proof' => $file,
        ]))
        ->assertRedirect();

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    expect($transaction->manual_proof_path)->not->toBeNull();
    expect($transaction->manual_proof_path)->toStartWith('manual-payment-proofs/');
    expect($transaction->manual_proof_path)->toEndWith('.png');

    // No user input in the name.
    expect($transaction->manual_proof_path)->not->toContain('my-bank-screenshot');
    expect(Storage::disk('private')->exists($transaction->manual_proof_path))->toBeTrue();

    // And not on the public disk.
    expect(Storage::disk('public')->exists($transaction->manual_proof_path))->toBeFalse();
});

it('accepts a PDF proof', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'proof' => UploadedFile::fake()->create('statement.pdf', 40, 'application/pdf'),
        ]))
        ->assertRedirect();

    expect(MarketplacePaymentTransaction::first()->manual_proof_path)->toEndWith('.pdf');
});

it('refuses a proof that is not an image or a PDF', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'proof' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
        ]))
        ->assertSessionHasErrors('proof');

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('refuses a proof over five megabytes', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'proof' => UploadedFile::fake()->create('huge.png', 6000, 'image/png'),
        ]))
        ->assertSessionHasErrors('proof');

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('works with no proof at all', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $token]))
        ->assertRedirect();

    expect(MarketplacePaymentTransaction::first()->manual_proof_path)->toBeNull();
});

it('never puts the proof path in a flash or session on failure', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => 'forged',
            'proof' => UploadedFile::fake()->image('shot.png'),
        ]));

    expect(json_encode(session()->all()))->not->toContain('manual-payment-proofs');
});

// ── the status page ───────────────────────────────────────────────────────

it('the owner can see their request status', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    $this->actingAs($user)
        ->get(route('marketplace.manual.show', ['transaction' => $transaction->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('marketplace/manual-pay-status')
            ->where('payment.status', 'pending_confirmation')
            ->where('payment.amount_etb', fn (string $a) => $a !== '')
        );
});

it('another workspace cannot see it', function () {
    [$user, , $token] = submitReady();
    [$stranger] = submitUser();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    $this->actingAs($stranger)
        ->get(route('marketplace.manual.show', ['transaction' => $transaction->id]))
        ->assertForbidden();
});

it('a guest cannot see it', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    // actingAs persists for the rest of the test, so sign them out first.
    $this->app['auth']->forgetGuards();

    $this->get(route('marketplace.manual.show', ['transaction' => $transaction->id]))
        ->assertRedirect(route('login'));
});

it('is not reachable by knowing the NF code', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    // The status route is addressed by the transaction id, not the code. The
    // code is printed in banking apps and appears in screenshots, so it cannot
    // be treated as a secret.
    expect(route('marketplace.manual.show', ['transaction' => $transaction->id]))
        ->toContain((string) $transaction->id)
        ->not->toContain($transaction->tx_ref);

    // And a stranger who somehow learned the code still gets nowhere, because
    // the code is not a route parameter at all.
    [$stranger] = submitUser();

    $this->actingAs($stranger)
        ->get(route('marketplace.manual.show', ['transaction' => $transaction->tx_ref]))
        ->assertNotFound();
});

it('lists the workspace requests with status', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $this->actingAs($user)
        ->get(route('marketplace.manual.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('marketplace/manual-pay-index')
            ->where('payments', fn ($rows) => $rows->count() === 1)
        );
});

it('never returns the payer phone or proof path to the list', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload([
        'token' => $token,
        'proof' => UploadedFile::fake()->image('shot.png'),
    ]));

    $payload = json_encode(
        $this->actingAs($user)->get(route('marketplace.manual.index'))->viewData('page')['props']
    );

    expect($payload)->not->toContain('+251911223344');
    expect($payload)->not->toContain('manual-payment-proofs');
    expect($payload)->toContain('"has_proof":true');
});

// ── I: admins are told ────────────────────────────────────────────────────

it('notifies every superadmin', function () {
    [$user, , $token] = submitReady();

    $admins = User::factory()->count(2)->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    foreach ($admins as $admin) {
        Notification::assertSentTo($admin, ManualPaymentSubmitted::class);
    }
});

it('notifies no ordinary users', function () {
    [$user, , $token] = submitReady();
    [$bystander] = submitUser();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    Notification::assertNotSentTo($bystander, ManualPaymentSubmitted::class);
});

it('the admin notification carries no proof path or payer phone', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload([
        'token' => $token,
        'proof' => UploadedFile::fake()->image('shot.png'),
    ]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();
    $notification = new ManualPaymentSubmitted($transaction);

    $payload = json_encode([
        'database' => $notification->toArray($transaction->user),
        'mail' => (string) $notification->toMail($transaction->user)->render(),
    ]);

    expect($payload)->not->toContain('manual-payment-proofs');
    expect($payload)->not->toContain('+251911223344');
    expect($payload)->not->toContain('payer_phone');
});

// ── validation ────────────────────────────────────────────────────────────

it('requires every field', function (string $field) {
    [$user, , $token] = submitReady();

    $payload = submitPayload(['token' => $token]);
    unset($payload[$field]);

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(MarketplacePaymentTransaction::count())->toBe(0);
})->with(['token', 'method', 'reference', 'payer_phone']);

it('refuses an unknown method', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'method' => 'carrier_pigeon',
        ]))
        ->assertSessionHasErrors('method');
});

it('refuses a reference that is too short to be real', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload([
            'token' => $token,
            'reference' => 'AB',
        ]))
        ->assertSessionHasErrors('reference');
});

it('keeps the method list in step with what is configured', function () {
    expect(array_keys(app(ManualPaymentService::class)->availableMethods()))->toBe(['telebirr']);

    app(MarketplaceTextSettingsService::class)->setMany([
        'manual_payment_cbe_birr_number' => ['value' => '1000123456789', 'is_enabled' => true],
        'manual_payment_cbe_birr_name' => ['value' => 'XCO Agency PLC', 'is_enabled' => true],
    ]);

    expect(array_keys(app(ManualPaymentService::class)->availableMethods()))
        ->toBe(['telebirr', 'cbe_birr']);
});

it('exposes no unrelated column on a stored manual row', function () {
    [$user, , $token] = submitReady();

    $this->actingAs($user)->post(route('marketplace.manual.store'), submitPayload(['token' => $token]));

    $transaction = MarketplacePaymentTransaction::firstOrFail();

    expect($transaction->chapa_ref)->toBeNull();
    expect($transaction->verified_at)->toBeNull();
    expect($transaction->provider_response)->toBeNull();
    expect($transaction->mode)->toBe('manual');
});

it('uses the phone service for the payer number', function () {
    // The same normalisation every other phone goes through.
    expect(app(PhoneNumberService::class)->normalize('0911223344'))->toBe('+251911223344');
    expect(app(PhoneNumberService::class)->normalize(PhoneNumberService::INVALID_MESSAGE))->toBeNull();
});

// ── H: rate limits ────────────────────────────────────────────────────────

it('refuses the sixth manual payment submission in an hour', function () {
    [$user] = submitUser();

    // Five requests that reach the controller. Each is stopped earlier by the
    // one-open-request or token rules, which is fine: the limiter counts
    // attempts, not successes.
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->post(route('marketplace.manual.store'), submitPayload(['token' => 'forged-'.$i]));
    }

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => 'forged-6']))
        ->assertStatus(429);
});

it('the submit limit is per user, not global', function () {
    [$user] = submitUser();
    [$other] = submitUser();

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($user)
            ->post(route('marketplace.manual.store'), submitPayload(['token' => 'forged-a-'.$i]));
    }

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => 'forged-a-7']))
        ->assertStatus(429);

    // A different user has their own budget.
    $this->actingAs($other)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => 'forged-b-0']))
        ->assertRedirect();
});

it('refuses the twenty-first instructions page in an hour', function () {
    [$user] = submitUser();

    for ($i = 0; $i < 20; $i++) {
        $this->actingAs($user)
            ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'monthly']));
    }

    $this->actingAs($user)
        ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'monthly']))
        ->assertStatus(429);
});

it('the instructions limit is separate from the submit limit', function () {
    [$user] = submitUser();

    for ($i = 0; $i < 20; $i++) {
        $this->actingAs($user)
            ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'monthly']));
    }

    // The instructions budget is spent, but submitting is a different limiter
    // with its own budget.
    $this->actingAs($user)
        ->get(route('marketplace.manual.create', ['plan' => 'standard', 'interval' => 'monthly']))
        ->assertStatus(429);

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => 'forged']))
        ->assertRedirect()
        ->assertSessionHasErrors('token');
});

// ── 3: the token is bound to its issuer ───────────────────────────────────

it('refuses a token issued to another user', function () {
    [$owner] = submitUser();
    [$stranger] = submitUser();

    $issued = app(ManualPaymentService::class)->issueInstructions($owner, 'standard', 'monthly');

    // The stranger presents the owner's perfectly valid token.
    $this->actingAs($stranger)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $issued['token']]))
        ->assertSessionHasErrors('token');

    // Nothing was created for either workspace.
    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('refuses a token issued for another workspace', function () {
    [$owner] = submitUser();
    [$colleague] = submitUser();

    $issued = app(ManualPaymentService::class)->issueInstructions($owner, 'standard', 'monthly');

    // Same user, different workspace. The token names the workspace it was
    // issued for, so it cannot be replayed into another one.
    $otherWorkspace = app(WorkspaceService::class)->createPersonalWorkspace($colleague);
    $colleague->update(['current_workspace_id' => $otherWorkspace->id]);
    $colleague->refresh();

    $this->actingAs($colleague)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $issued['token']]))
        ->assertSessionHasErrors('token');

    expect(MarketplacePaymentTransaction::count())->toBe(0);
});

it('a token still works for the user and workspace it was issued to', function () {
    [$user, $workspace, $token] = submitReady();

    $this->actingAs($user)
        ->post(route('marketplace.manual.store'), submitPayload(['token' => $token]))
        ->assertRedirect();

    expect(MarketplacePaymentTransaction::where('workspace_id', $workspace->id)->count())->toBe(1);
});
