<?php

use App\Enums\FreightPostStatus;
use App\Models\ContactReveal;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ContactRevealService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/**
 * A signed-in user with a workspace and a profile.
 *
 * @return array{0: User, 1: Workspace}
 */
function listingUser(bool $withPhone = true, array $profile = []): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], array_merge([
        'display_name' => 'Listing User',
        'phone' => $withPhone
            ? '+2519'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT)
            : null,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ], $profile));

    return [$user->fresh(), $workspace];
}

function listingPost(User $owner, array $attributes = []): FreightPost
{
    return FreightPost::factory()->for($owner)->create(array_merge([
        'status' => FreightPostStatus::Active->value,
    ], $attributes));
}

function listingTransporter(User $owner, ?string $whatsapp = null): TransporterProfile
{
    return TransporterProfile::create([
        'user_id' => $owner->id,
        'slug' => 'listing-'.Str::lower(Str::random(10)),
        'display_name' => 'Listing Transporter',
        'status' => 'approved',
        'whatsapp' => $whatsapp,
        'phone_visible' => true,
        'whatsapp_visible' => true,
        'email_visible' => true,
    ]);
}

it('does not reveal to a guest', function () {
    $owner = listingUser()[0];
    $post = listingPost($owner);

    $this->post(route('freight.reveal', $post->ulid))
        ->assertRedirect(route('login'));

    expect(ContactReveal::count())->toBe(0);
});

it('reveals a consignor contact from the listing without an interest', function () {
    [$owner] = listingUser(true, [
        'whatsapp' => '+251 91 222 3333',
    ]);
    [$viewer] = listingUser();

    $post = listingPost($owner);

    $response = $this->actingAs($viewer)
        ->postJson(route('freight.reveal', $post->ulid));

    $response->assertOk()
        ->assertJsonPath('status', 'allowed')
        ->assertJsonPath('contact.phone', $owner->profile->phone)
        ->assertJsonPath('contact.whatsapp', '+251 91 222 3333')
        ->assertJsonPath('contact.email', $owner->email);

    $ledger = ContactReveal::query()
        ->where('revealer_id', $viewer->id)
        ->where('revealed_id', $owner->id)
        ->first();

    expect($ledger)->not->toBeNull()
        ->and($ledger->freight_post_id)->toBe($post->id)
        ->and($ledger->interest_id)->toBeNull()
        ->and(FreightInterest::count())->toBe(0);
});

it('does not spend another reveal for the same consignor', function () {
    [$owner] = listingUser();
    [$viewer] = listingUser();
    $post = listingPost($owner);

    $this->actingAs($viewer)
        ->postJson(route('freight.reveal', $post->ulid))
        ->assertOk()
        ->assertJsonPath('status', 'allowed');

    $this->actingAs($viewer)
        ->postJson(route('freight.reveal', $post->ulid))
        ->assertOk()
        ->assertJsonPath('status', 'already');

    expect(ContactReveal::count())->toBe(1);
});

it('requires the viewer to have a phone before revealing', function () {
    [$owner] = listingUser();
    [$viewer] = listingUser(false);
    $post = listingPost($owner);

    $this->actingAs($viewer)
        ->postJson(route('freight.reveal', $post->ulid))
        ->assertStatus(422)
        ->assertJsonPath('requires_phone', true);

    expect(ContactReveal::count())->toBe(0);
});

it('hides a hidden listing from strangers', function () {
    [$owner] = listingUser();
    [$viewer] = listingUser();
    $post = listingPost($owner, ['hidden_at' => now()]);

    $this->actingAs($viewer)
        ->postJson(route('freight.reveal', $post->ulid))
        ->assertNotFound();
});

it('does not let the owner reveal their own contact', function () {
    [$owner] = listingUser();
    $post = listingPost($owner, ['hidden_at' => now()]);

    // The hidden-post rule lets the owner through, then the own-reveal guard
    // refuses. A 404 here would mean the hidden branch ran first by accident.
    $this->actingAs($owner)
        ->postJson(route('freight.reveal', $post->ulid))
        ->assertForbidden();
});

it('refuses a non-active listing to a stranger', function () {
    [$owner] = listingUser();
    [$viewer] = listingUser();
    $post = listingPost($owner, [
        'status' => FreightPostStatus::Draft->value,
    ]);

    $this->actingAs($viewer)
        ->postJson(route('freight.reveal', $post->ulid))
        ->assertForbidden();

    expect(ContactReveal::count())->toBe(0);
});

it('only opens the gate for a post owned by the counterparty', function () {
    $service = app(ContactRevealService::class);

    [$owner] = listingUser();
    [$thirdParty] = listingUser();
    [$otherCounterparty] = listingUser();

    $post = listingPost($owner);

    expect($service->canReveal($thirdParty, $owner, $post))->toBeTrue()
        ->and($service->canReveal($thirdParty, $otherCounterparty, $post))->toBeFalse();
});

it('reads the whatsapp from the profile and never the transporter copy', function () {
    [$owner] = listingUser(true, ['whatsapp' => '+251 91 111 1111']);
    [$viewer] = listingUser();

    listingTransporter($owner, '+251 92 222 2222');

    $post = listingPost($owner);
    $service = app(ContactRevealService::class);

    expect($service->contactDetailsFor($viewer, $owner, $post)['whatsapp'])
        ->toBe('+251 91 111 1111');

    Profile::where('user_id', $owner->id)->update(['whatsapp' => null]);

    // The legacy carrier row is no longer a source. A missing profile value is
    // missing, not an invitation to publish a stale copy.
    expect($service->contactDetailsFor($viewer, $owner->fresh(), $post)['whatsapp'])
        ->toBeNull();
});

it('honours a refused whatsapp on the profile', function () {
    [$owner] = listingUser(true, [
        'whatsapp' => '+251 91 111 1111',
        'whatsapp_visible' => false,
    ]);
    [$viewer] = listingUser();

    listingTransporter($owner, '+251 92 222 2222');

    $contact = app(ContactRevealService::class)
        ->contactDetailsFor($viewer, $owner, listingPost($owner));

    expect($contact['whatsapp'])->toBeNull()
        // The other channels still release.
        ->and($contact['phone'])->not->toBeNull()
        ->and($contact['email'])->toBe($owner->email);
});

it('blocks the sixth reveal through the listing endpoint', function () {
    [$viewer] = listingUser();
    $service = app(ContactRevealService::class);

    for ($i = 0; $i < 5; $i++) {
        [$consignor] = listingUser();
        $post = listingPost($consignor);

        $service->reveal($viewer, $consignor, $post);
    }

    [$sixth] = listingUser();
    $blocked = listingPost($sixth);

    $this->actingAs($viewer)
        ->post(route('freight.reveal', $blocked->ulid))
        ->assertRedirect()
        ->assertSessionHas('reveal_blocked', [
            'limit' => 5,
            'used' => 5,
        ])
        ->assertSessionHasErrors('contact');

    expect(ContactReveal::count())->toBe(5);
});
