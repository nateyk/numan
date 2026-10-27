<?php

use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The onboarding wizard is retired, not hidden.
 *
 * Signing in no longer puts a wizard between a person and the freight board.
 * The old URL is a permanent redirect to the page that owns the same profile,
 * and the profile itself is available to an unverified account because reading
 * and saving your own identity card is not a marketplace transaction.
 */
uses(RefreshDatabase::class);

it('redirects guests from onboarding to login', function (): void {
    $this->get('/onboarding')->assertRedirect(route('login'));
});

it('redirects a signed-in user from onboarding to the public profile page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/onboarding')->assertRedirect('/public-profile');
});

it('has removed every wizard step endpoint', function (string $path): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post($path)->assertNotFound();
})->with([
    '/onboarding/capabilities',
    '/onboarding/account-type',
    '/onboarding/details',
    '/onboarding/track-step',
]);

it('sends a stale onboarding post to the public profile', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/onboarding')
        ->assertRedirect('/public-profile');
});

it('lets an unverified user open and save their public profile', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    app(WorkspaceService::class)->createPersonalWorkspace($user);

    $this->actingAs($user)->get('/public-profile')->assertOk();

    $this->actingAs($user)->patch('/public-profile', [
        'display_name' => 'Unverified Person',
        'account_type' => 'personal',
        'phone' => '+251911000001',
        'posts_freight' => true,
        'provides_transport' => false,
    ])->assertRedirect();

    expect($user->fresh()->profile->display_name)->toBe('Unverified Person');
});

it('creates the profile through the first-post setup dialog', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($user);

    $this->actingAs($user->fresh())->post('/public-profile/setup', [
        'display_name' => 'Dialog Person',
        'account_type' => 'personal',
        'phone' => '0911223344',
        'posts_freight' => '1',
        'provides_transport' => '0',
    ])->assertRedirect();

    $profile = $user->fresh()->profile;

    expect($profile)->not->toBeNull()
        ->and($profile->display_name)->toBe('Dialog Person')
        ->and($profile->phone)->toBe('+251911223344')
        ->and((bool) $profile->posts_freight)->toBeTrue();
});

it('requires authentication for the public profile', function (): void {
    $this->get('/public-profile')->assertRedirect(route('login'));
});
