<?php

use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceService;

it('marks a personal one-member workspace as individual', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    $this->actingAs($user)->get('/freight')
        ->assertInertia(fn ($page) => $page->where('is_individual', true));
});

it('does not mark a multi-member workspace as individual', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($owner);
    $workspace->users()->attach(User::factory()->create()->id, ['role' => 'member']);
    $owner->switchWorkspace($workspace);

    $this->actingAs($owner)->get('/freight')
        ->assertInertia(fn ($page) => $page->where('is_individual', false));
});

it('redirects an individual user from the dashboard to freight', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/freight');
});

it('shares a compact public profile without contact details', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    Profile::factory()->for($user)->create([
        'display_name' => 'Ada',
        'phone' => '+251911000001',
        'whatsapp' => '+251911000002',
        'email' => 'ada@example.com',
        'posts_freight' => true,
        'provides_transport' => true,
        'verification_status' => 'verified',
    ]);

    $response = $this->actingAs($user)->get('/freight');
    $response
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.public_profile.display_name', 'Ada')
            ->where('auth.user.public_profile.provides_transport', true)
            ->where('auth.user.public_profile.verification_status', 'verified')
            ->missing('auth.user.public_profile.phone')
            ->missing('auth.user.public_profile.whatsapp')
            ->missing('auth.user.public_profile.email')
        );
});

it('drops the invite step from the checklist for an individual', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    $steps = collect(
        $this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'),
    );

    expect($steps->pluck('id'))->not->toContain('invite_member');
});
