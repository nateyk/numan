<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\PhoneNumberService;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const STEP_PHONE = '+251911000111';
const STEP_EMAIL = 'phone-step@example.test';

/**
 * A user with a workspace and a profile that has no phone number.
 *
 * This is the "existing user who joined before the phone requirement" case.
 */
function stepUserWithoutPhone(bool $providesTransport = false): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'email' => 'step-'.Str::lower(Str::random(10)).'@example.test',
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => ! $providesTransport,
        'provides_transport' => $providesTransport,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'No Phone Yet',
        'phone' => null,
    ]);

    return [$user->fresh(), $workspace];
}

/**
 * Give a user a phone number, as the inline step would.
 */
function stepSavePhone($test, User $user, string $phone = '0911000111'): void
{
    $test->actingAs($user)->post(route('profile.phone.update'), ['phone' => $phone])->assertRedirect();
}

// ── normalisation ─────────────────────────────────────────────────────────

it('normalises every accepted Ethiopian format to E.164', function (string $input, string $expected) {
    expect(app(PhoneNumberService::class)->normalize($input))->toBe($expected);
})->with([
    'already E.164' => ['+251911234567', '+251911234567'],
    'bare country code' => ['251911234567', '+251911234567'],
    'international 00 prefix' => ['00251911234567', '+251911234567'],
    'local 09' => ['0911234567', '+251911234567'],
    'local 07' => ['0711234567', '+251711234567'],
    'spaced' => ['+251 91 123 4567', '+251911234567'],
    'dashed' => ['091-123-4567', '+251911234567'],
    'parenthesised' => ['+251 (91) 123 4567', '+251911234567'],
    'dotted' => ['091.123.4567', '+251911234567'],
]);

it('rejects anything that is not an Ethiopian mobile number', function (?string $input) {
    expect(app(PhoneNumberService::class)->normalize($input))->toBeNull();
})->with([
    'empty' => [''],
    'null' => [null],
    'letters' => ['not a number'],
    'too short' => ['091123456'],
    'too long' => ['091123456789'],
    'landline prefix 8' => ['0811234567'],
    'landline prefix 2' => ['0211234567'],
    'other country' => ['+12025550143'],
    'digits only wrong length' => ['1234567890'],
]);

it('saves the phone normalised, not as typed', function () {
    [$user] = stepUserWithoutPhone();

    stepSavePhone($this, $user, '091 123 4567');

    expect($user->profile()->first()->phone)->toBe('+251911234567');
});

it('refuses an invalid phone with a clear message and saves nothing', function () {
    [$user] = stepUserWithoutPhone();

    $this->actingAs($user)
        ->post(route('profile.phone.update'), ['phone' => '0811234567'])
        ->assertSessionHasErrors('phone');

    expect($user->profile()->first()->phone)->toBeNull();
});

it('requires onboarding to collect a phone and normalises it', function () {
    $user = User::factory()->unonboarded()->create(['email_verified_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($user);

    $this->actingAs($user)->post(route('onboarding.capabilities'), [
        'posts_freight' => true,
        'provides_transport' => false,
    ])->assertRedirect();

    $this->actingAs($user)->post(route('onboarding.account-type'), [
        'account_type' => 'personal',
    ])->assertRedirect();

    $locationId = Location::factory()->create()->id;

    $this->actingAs($user)->post(route('onboarding.details'), [
        'display_name' => 'Onboarded Person',
        'phone' => '0711234567',
        'primary_location_id' => $locationId,
    ])->assertRedirect(route('dashboard'));

    expect($user->profile()->first()->phone)->toBe('+251711234567');
});

it('onboarding rejects an invalid phone', function () {
    $user = User::factory()->unonboarded()->create(['email_verified_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($user);

    $this->actingAs($user)->post(route('onboarding.capabilities'), [
        'posts_freight' => true,
        'provides_transport' => false,
    ])->assertRedirect();

    $this->actingAs($user)->post(route('onboarding.account-type'), [
        'account_type' => 'personal',
    ])->assertRedirect();

    $locationId = Location::factory()->create()->id;

    $this->actingAs($user)->post(route('onboarding.details'), [
        'display_name' => 'Onboarded Person',
        'phone' => '0211234567',
        'primary_location_id' => $locationId,
    ])->assertSessionHasErrors('phone');

    expect($user->profile()->first()->phone)->toBeNull();
});

// ── inline step, not a hard block ─────────────────────────────────────────

it('shows the inline step instead of blocking a post, and the post then works', function () {
    [$user, $workspace] = stepUserWithoutPhone();

    $before = FreightPost::count();

    // Not a 403: a bounce back with the flag the layout renders from.
    $this->actingAs($user)
        ->post(route('freight.store'))
        ->assertRedirect()
        ->assertSessionHas('requires_phone', true)
        ->assertSessionHasErrors('freight');

    // Nothing was created before they confirmed.
    expect(FreightPost::count())->toBe($before);

    // The layout reads this shared prop to decide to render the field.
    $this->actingAs($user)
        ->get(route('freight.index'))
        ->assertInertia(fn ($page) => $page->where('requires_phone', true));

    stepSavePhone($this, $user);

    $this->actingAs($user)->post(route('freight.store'))->assertRedirect();

    expect(FreightPost::count())->toBe($before + 1);
    expect(FreightPost::latest('id')->first()->user_id)->toBe($user->id);
});

it('shows the inline step instead of blocking an interest, and the interest then works', function () {
    [$consignor, $workspace] = stepUserWithoutPhone();
    [$transporter] = stepUserWithoutPhone(true);

    // Expressing interest needs a transporter profile; that check runs first,
    // so give them one. The phone is deliberately still absent.
    TransporterProfile::firstOrCreate(['user_id' => $transporter->id], [
        'slug' => 'step-'.Str::lower(Str::random(8)),
        'display_name' => 'Step Transporter',
        'status' => 'approved',
        'phone_visible' => true,
        'email_visible' => true,
    ]);

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'Phone step cargo',
    ]);

    $this->actingAs($transporter)
        ->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['message' => 'hi'])
        ->assertRedirect()
        ->assertSessionHas('requires_phone', true)
        ->assertSessionHasErrors('interest');

    expect(FreightInterest::where('freight_post_id', $post->id)->count())->toBe(0);

    stepSavePhone($this, $transporter);

    $this->actingAs($transporter)
        ->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['message' => 'hi'])
        ->assertRedirect();

    expect(FreightInterest::where('freight_post_id', $post->id)->count())->toBe(1);
});

it('shows the inline step instead of blocking a reveal, and the reveal then works', function () {
    [$consignor, $workspace] = stepUserWithoutPhone();
    Profile::where('user_id', $consignor->id)->update(['phone' => '+251911000222']);

    $transporter = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($transporter);
    Profile::firstOrCreate(['user_id' => $transporter->id], [
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'display_name' => 'Revealed Transporter',
        'phone' => STEP_PHONE,
    ]);

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'Phone step cargo',
    ]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);

    // Remove the viewer's phone so the reveal needs the step.
    Profile::where('user_id', $consignor->id)->update(['phone' => null]);

    // JSON callers get a machine-readable signal, not a 403.
    $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertStatus(422)
        ->assertJsonPath('requires_phone', true);

    // No contact data in the blocked response.
    expect(json_encode($this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->json()))->not->toContain(STEP_PHONE);

    stepSavePhone($this, $consignor);

    $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertOk()
        ->assertJsonPath('contact.phone', STEP_PHONE);
});

it('the blocked reveal bounces back with the inline flag rather than a 403', function () {
    [$consignor, $workspace] = stepUserWithoutPhone();

    $transporter = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($transporter);
    Profile::firstOrCreate(['user_id' => $transporter->id], [
        'provides_transport' => true,
        'display_name' => 'Revealed Transporter',
        'phone' => STEP_PHONE,
    ]);

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
    ]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($consignor)
        ->post(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertRedirect()
        ->assertSessionHas('requires_phone', true);
});

// ── reveal source order ───────────────────────────────────────────────────

/**
 * Build a counterparty with exactly the contact fields a test needs.
 */
function stepCounterparty(array $profileAttrs = [], array $transporterAttrs = []): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'email' => 'counterparty-source@example.test',
    ]);

    app(WorkspaceService::class)->createPersonalWorkspace($user);

    Profile::firstOrCreate(['user_id' => $user->id], array_merge([
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'display_name' => 'Source Order Transporter',
    ], $profileAttrs));

    if ($transporterAttrs !== []) {
        TransporterProfile::firstOrCreate(['user_id' => $user->id], array_merge([
            'slug' => 'src-'.Str::lower(Str::random(8)),
            'display_name' => 'Source Order Transporter',
            'status' => 'approved',
            'phone_visible' => true,
            'email_visible' => true,
        ], $transporterAttrs));
    }

    return $user->fresh();
}

function stepInterestBetween(User $consignor, User $transporter, array $interestAttrs = []): FreightInterest
{
    $workspace = $consignor->currentWorkspace;

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $workspace?->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'source order cargo',
    ]);

    return FreightInterest::create(array_merge([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ], $interestAttrs));
}

/**
 * The reveal gate also requires the VIEWER to have a phone, so give them one.
 */
function stepGiveViewerPhone($test, User $viewer): void
{
    Profile::where('user_id', $viewer->id)->update(['phone' => '+251911000999']);
}

function stepReveal($test, User $consignor, FreightInterest $interest)
{
    return $test->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));
}

it('reveal source 1: the profile phone wins over everything else', function () {
    [$consignor] = stepUserWithoutPhone();
    stepGiveViewerPhone($this, $consignor);

    $transporter = stepCounterparty(
        ['phone' => '+251911000001'],
        ['phone' => '+251911111111', 'email' => 'tp@example.test'],
    );

    $interest = stepInterestBetween($consignor, $transporter, ['contact_phone' => '+251911222222']);

    stepReveal($this, $consignor, $interest)
        ->assertOk()
        ->assertJsonPath('contact.phone', '+251911000001');
});

it('reveal source 2: the transporter profile phone is next', function () {
    [$consignor] = stepUserWithoutPhone();
    stepGiveViewerPhone($this, $consignor);

    $transporter = stepCounterparty(
        ['phone' => null],
        ['phone' => '+251911111111'],
    );

    $interest = stepInterestBetween($consignor, $transporter, ['contact_phone' => '+251911222222']);

    stepReveal($this, $consignor, $interest)
        ->assertOk()
        ->assertJsonPath('contact.phone', '+251911111111');
});

it('reveal source 3: the legacy interest phone is the last phone fallback', function () {
    [$consignor] = stepUserWithoutPhone();
    stepGiveViewerPhone($this, $consignor);

    $transporter = stepCounterparty(['phone' => null]);

    $interest = stepInterestBetween($consignor, $transporter, ['contact_phone' => '+251911222222']);

    stepReveal($this, $consignor, $interest)
        ->assertOk()
        ->assertJsonPath('contact.phone', '+251911222222');
});

it('reveal source 4: email is the final fallback when no phone exists', function () {
    [$consignor] = stepUserWithoutPhone();
    stepGiveViewerPhone($this, $consignor);

    $transporter = stepCounterparty(['phone' => null]);

    $interest = stepInterestBetween($consignor, $transporter);

    $response = stepReveal($this, $consignor, $interest)->assertOk();

    expect($response->json('contact.phone'))->toBeNull();
    expect($response->json('contact.email'))->toBe('counterparty-source@example.test');
});

it('never exposes a legacy interest phone on the consignor side', function () {
    // The consignor's own interest row was filled in by the transporter, so a
    // contact_phone on the consignor's row must not be surfaced to the
    // transporter viewing them.
    [$viewer] = stepUserWithoutPhone(true);
    Profile::where('user_id', $viewer->id)->update(['phone' => '+251911333333']);

    $consignor = stepCounterparty(['phone' => null]);

    $interest = stepInterestBetween($consignor, $viewer, [
        'contact_phone' => '+251911999999',
    ]);

    $response = $this->actingAs($viewer)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertOk();

    // The viewer's own interest row is the context, but the counterparty is the
    // consignor, so the legacy column must not apply to them.
    expect(json_encode($response->json()))->not->toContain('+251911999999');
    expect($response->json('contact.phone'))->toBeNull();
    expect($response->json('contact.email'))->toBe('counterparty-source@example.test');
});

it('never exposes bank columns through a reveal, whatever is on the interest', function () {
    [$consignor] = stepUserWithoutPhone();
    stepGiveViewerPhone($this, $consignor);

    $transporter = stepCounterparty(['phone' => '+251911000001']);

    $interest = stepInterestBetween($consignor, $transporter, [
        'bank_account_name' => 'SECRET BANK NAME',
        'bank_name' => 'SECRET BANK',
        'bank_account_number' => '100055500001',
        'bank_iban' => 'ET0000000001',
        'bank_swift' => 'SECRETSWIFT',
    ]);

    $payload = json_encode(stepReveal($this, $consignor, $interest)->assertOk()->json());

    foreach (['SECRET BANK NAME', 'SECRET BANK', '100055500001', 'ET0000000001', 'SECRETSWIFT'] as $needle) {
        expect($payload)->not->toContain($needle);
    }

    // No bank key exists in the response at all.
    expect(array_keys(stepReveal($this, $consignor, $interest)->json('contact')))
        ->toBe(['name', 'phone', 'email', 'whatsapp']);
});

it('a transporter who turned phone visibility off does not fall through to another source', function () {
    [$consignor] = stepUserWithoutPhone();
    stepGiveViewerPhone($this, $consignor);

    $transporter = stepCounterparty(
        ['phone' => null],
        ['phone' => '+251911111111', 'phone_visible' => false],
    );

    $interest = stepInterestBetween($consignor, $transporter, ['contact_phone' => '+251911222222']);

    stepReveal($this, $consignor, $interest)->assertOk()->assertJsonPath('contact.phone', null);
});

it('a connection reveal passes the connection as context, not the interest', function () {
    [$consignor, $workspace] = stepUserWithoutPhone();
    stepGiveViewerPhone($this, $consignor);

    $transporter = stepCounterparty(
        ['phone' => null],
        ['phone' => '+251911111111'],
    );

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
    ]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'selected',
    ]);

    $connection = MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $this->actingAs($consignor)
        ->postJson(route('connections.reveal', ['public_id' => $connection->public_id]))
        ->assertOk()
        ->assertJsonPath('contact.phone', '+251911111111');
});
