<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The dashboard checklist widget.
 *
 * It listed "add a bio and profile photo", "invite a team member" and "connect
 * billing" — SaaS housekeeping that says nothing about whether somebody can trade
 * freight. The marketplace steps are prepended for anyone who has picked up a
 * capability, and name the fields that are actually missing rather than pointing
 * at a page that has no phone number on it.
 */
uses(RefreshDatabase::class);

function checklistUser(array $profileOverrides = []): User
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    // The checklist's original account steps include inviting a team member, so
    // these cases model a shared workspace rather than an individual account.
    $workspace->users()->attach(User::factory()->create()->id, ['role' => 'member']);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(
        ['user_id' => $user->id],
        array_merge([
            'display_name' => 'Someone',
            'phone' => '+251911000001',
            'posts_freight' => false,
            'provides_transport' => false,
        ], $profileOverrides),
    );

    return $user->refresh();
}

it('leaves a user with no capability on the original checklist', function () {
    $user = checklistUser();

    $this->actingAs($user)
        ->getJson('/onboarding-checklist')
        ->assertOk()
        ->assertJsonPath('total', 4);
});

it('lists the marketplace steps first for somebody who acts in the marketplace', function () {
    $user = checklistUser(['posts_freight' => true, 'display_name' => '', 'phone' => '']);

    $steps = $this->actingAs($user)
        ->getJson('/onboarding-checklist')
        ->json('steps');

    expect($steps[0]['id'])->toBe('posts_freight_ready');
});

it('names the fields that are missing', function () {
    $user = checklistUser(['posts_freight' => true, 'display_name' => '', 'phone' => '']);

    $steps = $this->actingAs($user)
        ->getJson('/onboarding-checklist')
        ->json('steps');

    expect($steps[0]['description'])->toContain('display_name', 'phone')
        ->and($steps[0]['href'])->toBe('/public-profile');
});

it('lists a step per capability the user has chosen', function () {
    $user = checklistUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'display_name' => '',
        'phone' => '',
    ]);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    expect($steps->pluck('id'))
        ->toContain('posts_freight_ready', 'provides_transport_ready');
});

it('says nothing about a capability the user has not chosen', function () {
    // A consignor must not be nagged about a base location for a capability they
    // never picked up.
    $user = checklistUser(['posts_freight' => true, 'display_name' => '', 'phone' => '']);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    expect($steps->pluck('id'))->not->toContain('provides_transport_ready');
});

it('drops a capability once it is ready', function () {
    // Noise is what makes people dismiss a checklist for good.
    $user = checklistUser([
        'posts_freight' => true,
        'display_name' => 'Ready',
        'phone' => '+251911000002',
    ]);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    expect($steps->pluck('id'))->not->toContain('posts_freight_ready')
        ->and($steps->pluck('id'))->toContain('complete_profile');
});

it('keeps a capability that is chosen but blocked on its own extra fields', function () {
    // Identity is complete, so posting is ready, but carrying additionally needs a
    // base and a vehicle. Reporting them as one figure is how somebody is shown as
    // ready and then refused at the gate.
    $user = checklistUser([
        'provides_transport' => true,
        'display_name' => 'Ready Identity',
        'phone' => '+251911000003',
    ]);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    $carrying = $steps->firstWhere('id', 'provides_transport_ready');

    expect($carrying)->not->toBeNull()
        ->and($carrying['description'])->toContain('primary_location_id');
});

it('keeps the account steps alongside the marketplace ones', function () {
    $user = checklistUser(['posts_freight' => true, 'display_name' => '', 'phone' => '']);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    // Nothing working is removed. The account steps still apply; they just stop
    // being the first thing a freight user is shown.
    expect($steps->pluck('id'))->toContain(
        'complete_profile',
        'enable_2fa',
        'invite_member',
        'connect_billing',
    );
});

it('still counts the account steps toward progress', function () {
    $user = User::factory()->create(['bio' => 'I move things.', 'two_factor_confirmed_at' => now()]);
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Shared Workspace']);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate([
        'user_id' => $user->id,
        'posts_freight' => false,
        'provides_transport' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/onboarding-checklist');

    $response->assertOk()->assertJsonPath('total', 4);

    expect($response->json('completed'))->toBe(2);
});

it('stays empty for a dismissed checklist', function () {
    $user = checklistUser(['posts_freight' => true]);
    // Dismissal lives on the user, not the profile.
    $user->forceFill(['onboarding_checklist_dismissed_at' => now()])->save();

    $this->actingAs($user)
        ->getJson('/onboarding-checklist')
        ->assertOk()
        ->assertJsonPath('dismissed', true)
        ->assertJsonPath('steps', []);
});

it('does not fall over for a user with no profile row', function () {
    // Every new account loads a dashboard before it has ever opened onboarding.
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $workspace->users()->attach(User::factory()->create()->id, ['role' => 'member']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->getJson('/onboarding-checklist')
        ->assertOk()
        ->assertJsonPath('total', 4);
});

it('reports a complete carrier as needing nothing more', function () {
    $base = Location::factory()->create();

    $user = checklistUser([
        'provides_transport' => true,
        'display_name' => 'Fully Ready',
        'phone' => '+251911000004',
        'primary_location_id' => $base->id,
    ]);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    expect($steps->pluck('id'))->not->toContain('provides_transport_ready');
});

it('requires authentication', function () {
    $this->getJson('/onboarding-checklist')->assertUnauthorized();
});
