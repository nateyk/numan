<?php

use App\Enums\FreightPostStatus;
use App\Http\Requests\StoreFreightInterestRequest;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\FreightInterestSubmitted;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Leak audit: every path that used to hand a counterparty's contact details to
 * the browser before any reveal.
 *
 * Each test creates users with unique, greppable phone/email/whatsapp strings
 * and asserts those strings are absent from the FULL serialised Inertia
 * payload. Checking that a key is missing is not enough: the value could still
 * appear under a different key.
 */
uses(RefreshDatabase::class);

const UNIQUE_PHONE_CONSIGNOR = '+251911110001';
const UNIQUE_EMAIL_CONSIGNOR = 'leak-consignor-zz@example.test';
const UNIQUE_PHONE_TRANSPORTER = '+251922220002';
const UNIQUE_EMAIL_TRANSPORTER = 'leak-transporter-zz@example.test';
const UNIQUE_WHATSAPP = '+251933330003';

/**
 * Create a consignor with a profile phone and no transporter profile.
 *
 * @return array{0: User, 1: Workspace}
 */
function leakConsignor(): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'email' => UNIQUE_EMAIL_CONSIGNOR,
    ]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Leaky Consignor',
        'phone' => UNIQUE_PHONE_CONSIGNOR,
    ]);

    return [$user, $workspace];
}

/**
 * Create an approved transporter with phone, email and whatsapp set.
 */
function leakTransporter(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'email' => UNIQUE_EMAIL_TRANSPORTER,
    ]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    // Readiness reads the public profile now, so the base location has to live
    // there as well as on the legacy carrier row.
    $base = Location::firstOrCreate(
        ['name' => 'Adama'],
        ['type' => 'city', 'region_name' => 'Oromia', 'latitude' => 8.54, 'longitude' => 39.27]
    );

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Leaky Transporter',
        'phone' => UNIQUE_PHONE_TRANSPORTER,
        'primary_location_id' => $base->id,
    ]);

    TransporterProfile::firstOrCreate(['user_id' => $user->id], [
        'slug' => 'leaky-'.Str::lower(Str::random(8)),
        'display_name' => 'Leaky Transporter',
        'legal_name' => 'LEAKY LEGAL NAME',
        'status' => 'approved',
        'phone' => UNIQUE_PHONE_TRANSPORTER,
        'primary_location_id' => $base->id,
        'email' => UNIQUE_EMAIL_TRANSPORTER,
        'whatsapp' => UNIQUE_WHATSAPP,
        'phone_visible' => true,
        'email_visible' => true,
        'whatsapp_visible' => true,
    ]);

    return $user;
}

function leakActivePost(User $consignor, $workspace): FreightPost
{
    return FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $workspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'Leak audit cargo',
    ]);
}

/**
 * Contact strings belonging to the consignor.
 *
 * @return list<string>
 */
function consignorStrings(): array
{
    return [UNIQUE_PHONE_CONSIGNOR, UNIQUE_EMAIL_CONSIGNOR];
}

/**
 * Contact strings belonging to the transporter.
 *
 * @return list<string>
 */
function transporterStrings(): array
{
    return [
        UNIQUE_PHONE_TRANSPORTER,
        UNIQUE_EMAIL_TRANSPORTER,
        UNIQUE_WHATSAPP,
        'LEAKY LEGAL NAME',
    ];
}

/**
 * Assert none of the given strings appear anywhere in the Inertia payload.
 *
 * @param  list<string>  $needles
 */
function assertPayloadOmits($response, array $needles): void
{
    $payload = json_encode($response->viewData('page')['props'] ?? []);

    foreach ($needles as $needle) {
        expect($payload)->not->toContain($needle);
    }
}

/**
 * Assert none of the given strings appear in a raw payload.
 *
 * @param  list<string>  $needles
 */
function assertPayloadOmitsString(string $payload, array $needles): void
{
    foreach ($needles as $needle) {
        expect($payload)->not->toContain($needle);
    }
}

/**
 * A viewer with a workspace, for routes behind the workspace middleware.
 */
function leakBystander(): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

// ── 1. interest list, consignor view ──────────────────────────────────────

it('leak 1: the consignor interest list carries no contact or bank data', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    // Pre-seed a legacy row that still holds every sensitive field.
    FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
        'contact_name' => 'Leaky Transporter',
        'contact_phone' => UNIQUE_PHONE_TRANSPORTER,
        'contact_email' => UNIQUE_EMAIL_TRANSPORTER,
        'bank_account_name' => 'LEAKY BANK NAME',
        'bank_name' => 'LEAKY BANK',
        'bank_account_number' => '1000000001',
        'bank_iban' => 'ET0000001',
        'bank_swift' => 'CBEETAA',
    ]);

    $response = $this->actingAs($consignor)->get(route('freight.interests.index', ['ulid' => $post->ulid]));

    $response->assertOk();
    // The viewer is the consignor, so only the transporter's details are forbidden.
    assertPayloadOmits($response, transporterStrings());

    $raw = json_encode($response->viewData('page')['props']);
    expect($raw)->not->toContain('LEAKY BANK NAME');
    expect($raw)->not->toContain('1000000001');
    expect($raw)->not->toContain('ET0000001');
    expect($raw)->not->toContain('CBEETAA');
});

// ── 2. public freight show and discovery ─────────────────────────────────

it('leak 2: the public freight show page exposes no owner email or phone', function () {
    [$consignor, $workspace] = leakConsignor();
    $post = leakActivePost($consignor, $workspace);

    $response = $this->get("/freight/{$post->ulid}");

    $response->assertOk();
    assertPayloadOmits($response, array_merge(consignorStrings(), transporterStrings()));
});

it('leak 2b: the public discovery listing exposes no owner email or phone', function () {
    [$consignor, $workspace] = leakConsignor();
    leakActivePost($consignor, $workspace);

    $response = $this->get(route('freight.discovery'));

    $response->assertOk();
    assertPayloadOmits($response, array_merge(consignorStrings(), transporterStrings()));
});

// ── 3. transporter directory and profile ─────────────────────────────────

it('leak 3: the transporter directory exposes no phone, whatsapp or email', function () {
    leakTransporter();

    $response = $this->actingAs(leakBystander())->get(route('transporters.index'));

    $response->assertOk();
    assertPayloadOmits($response, transporterStrings());
});

it('leak 3b: the public transporter profile exposes no phone, whatsapp or legal name', function () {
    $transporter = leakTransporter();
    $slug = TransporterProfile::where('user_id', $transporter->id)->first()->slug;

    $response = $this->actingAs(leakBystander())->get(route('transporters.show', ['slug' => $slug]));

    $response->assertOk();
    assertPayloadOmits($response, transporterStrings());
});

// ── 4. accepting an interest must not auto-reveal ────────────────────────

it('leak 4: accepting an interest does not return contact details', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);

    $response = $this->actingAs($consignor)
        ->post(route('freight.interests.accept', ['interest' => $interest->id]));

    $response->assertRedirect();
    // A redirect carries no contact data in its body or flash session.
    $flash = json_encode(session()->all());
    assertPayloadOmitsString($response->getContent().$flash, transporterStrings());
    expect(MarketplaceConnection::count())->toBe(1);
});

// ── 5. connection page ───────────────────────────────────────────────────

it('leak 5: the connection page carries no contact data before reveal', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);

    $connection = MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $response = $this->actingAs($consignor)
        ->get(route('connections.show', ['public_id' => $connection->public_id]));

    $response->assertOk();
    assertPayloadOmits($response, transporterStrings());
    // Nothing contact-shaped in the payload at all before a reveal.
    expect($response->viewData('page')['props']['revealedContacts'])->toBeNull();
    expect($response->viewData('page')['props']['alreadyRevealed'])->toBeFalse();
});

it('leak 5b: the connection index carries no contact data', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'selected',
        'message' => 'interested',
    ]);

    MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $response = $this->actingAs($transporter)->get(route('connections.index'));

    $response->assertOk();
    assertPayloadOmits($response, consignorStrings());
});

// ── 6. transporter's own interest list ────────────────────────────────────

it('leak 6: the transporter interest list exposes no consignor contact or bank data', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
        'contact_phone' => UNIQUE_PHONE_TRANSPORTER,
        'contact_email' => UNIQUE_EMAIL_TRANSPORTER,
        'bank_account_number' => '1000000002',
    ]);

    $response = $this->actingAs($transporter)->get(route('freight.my-interests'));

    $response->assertOk();
    assertPayloadOmits($response, consignorStrings());

    $raw = json_encode($response->viewData('page')['props']);
    expect($raw)->not->toContain('1000000002');
});

// ── 7. notifications ─────────────────────────────────────────────────────

it('leak 7: the interest-submitted notification carries no contact or bank data', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
        'contact_phone' => UNIQUE_PHONE_TRANSPORTER,
        'contact_email' => UNIQUE_EMAIL_TRANSPORTER,
        'bank_account_number' => '1000000003',
    ]);

    $notification = new FreightInterestSubmitted($interest, $post);

    $payload = json_encode([
        'array' => $notification->toArray($consignor),
        'mail' => (string) $notification->toMail($consignor)->render(),
    ]);

    assertPayloadOmitsString($payload, transporterStrings());

    expect($payload)->not->toContain('1000000003');
});

// ── reveal still works, and is gated ─────────────────────────────────────

it('reveal returns the contact details to an entitled party', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);

    $response = $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBe(UNIQUE_PHONE_TRANSPORTER);
});

it('reveal works for the transporter seeing the consignor', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);

    $response = $this->actingAs($transporter)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBe(UNIQUE_PHONE_CONSIGNOR);
});

it('a stranger cannot reveal', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);

    $stranger = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $this->actingAs($stranger)
        ->post(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertForbidden();
});

it('a rejected interest cannot be revealed', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'rejected',
        'message' => 'interested',
    ]);

    $this->actingAs($consignor)
        ->post(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertForbidden();
});

it('a withdrawn interest cannot be revealed', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'withdrawn',
        'message' => 'interested',
    ]);

    $this->actingAs($consignor)
        ->post(route('freight.interests.reveal', ['interest' => $interest->id]))
        ->assertForbidden();
});

it('profile visibility flags are respected on reveal', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    Profile::where('user_id', $transporter->id)->update([
        'phone_visible' => false,
        'email_visible' => false,
        'whatsapp_visible' => false,
    ]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $transporter->id,
        'status' => 'submitted',
        'message' => 'interested',
    ]);

    $response = $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBeNull();
    expect($response->json('contact.email'))->toBeNull();
    expect($response->json('contact.whatsapp'))->toBeNull();
    // Name is always visible.
    expect($response->json('contact.name'))->toBe('Leaky Transporter');
});

// ── collection stopped ───────────────────────────────────────────────────

it('expressing interest no longer stores contact or bank fields', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    $this->actingAs($transporter)
        ->post(route('freight.interests.store', ['ulid' => $post->ulid]), [
            'message' => 'interested',
            'contact_phone' => UNIQUE_PHONE_TRANSPORTER,
            'contact_email' => UNIQUE_EMAIL_TRANSPORTER,
            'bank_account_number' => '1000000009',
        ])
        ->assertRedirect();

    $interest = FreightInterest::where('freight_post_id', $post->id)->first();

    expect($interest)->not->toBeNull();
    expect($interest->contact_phone)->toBeNull();
    expect($interest->contact_email)->toBeNull();
    expect($interest->bank_account_number)->toBeNull();
    expect($interest->bank_iban)->toBeNull();
    expect($interest->bank_swift)->toBeNull();
    expect($interest->bank_account_name)->toBeNull();
    expect($interest->bank_name)->toBeNull();
});

it('the request no longer accepts bank or interest contact fields', function () {
    $rules = (new StoreFreightInterestRequest)->rules();

    expect(array_keys($rules))->toBe(['message']);

    foreach (['contact_phone', 'contact_email', 'bank_account_name', 'bank_name', 'bank_account_number', 'bank_iban', 'bank_swift'] as $field) {
        expect($rules)->not->toHaveKey($field);
    }
});

it('a user without a phone on file cannot express interest', function () {
    [$consignor, $workspace] = leakConsignor();
    $transporter = leakTransporter();
    $post = leakActivePost($consignor, $workspace);

    Profile::where('user_id', $transporter->id)->update(['phone' => null]);

    $this->actingAs($transporter)
        ->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['message' => 'hi'])
        ->assertSessionHasErrors('interest');

    expect(FreightInterest::where('freight_post_id', $post->id)->count())->toBe(0);
});

it('a user without a phone on file cannot post freight', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'phone' => null,
    ]);

    $before = FreightPost::count();

    $this->actingAs($user)->post('/my-freight')->assertSessionHasErrors('freight');

    expect(FreightPost::count())->toBe($before);
});
