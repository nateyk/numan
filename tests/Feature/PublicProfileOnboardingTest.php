<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests from onboarding to login', function (): void {
    $this->get(route('onboarding.index'))->assertRedirect(route('login'));
});

it('shows onboarding wizard for users without a public profile', function (): void {
    $user = User::factory()->unonboarded()->create();
    Location::factory()->create(['name' => 'Addis Ababa', 'region_name' => 'Addis Ababa']);

    $response = $this->actingAs($user)->get(route('onboarding.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/index')
        ->has('profile')
        ->has('currentStep')
        ->has('locations')
    );
});

it('is resumable — currentStep drives the wizard', function (): void {
    $user = User::factory()->unonboarded()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'account_type',
    ]);

    $response = $this->actingAs($user)->get(route('onboarding.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('currentStep', 'account_type')
        ->where('profile.posts_freight', true)
    );
});

it('validates step 1 — at least one capability required', function (): void {
    $user = User::factory()->unonboarded()->create();

    $response = $this->actingAs($user)->post(route('onboarding.capabilities'), [
        'posts_freight' => false,
        'provides_transport' => false,
    ]);

    $response->assertSessionHasErrors(['posts_freight']);
});

it('completes step 1 and advances to account_type', function (): void {
    $user = User::factory()->unonboarded()->create();

    $response = $this->actingAs($user)->post(route('onboarding.capabilities'), [
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($user->fresh()->profile->onboarding_step)->toBe('account_type')
        ->and($user->fresh()->profile->posts_freight)->toBeTrue();
});

it('validates step 2 — account_type required and must be personal or business', function (): void {
    $user = User::factory()->unonboarded()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'onboarding_step' => 'account_type',
    ]);

    $this->actingAs($user)->post(route('onboarding.account-type'), [
        'account_type' => 'invalid',
    ])->assertSessionHasErrors(['account_type']);

    $this->actingAs($user)->post(route('onboarding.account-type'), [
        'account_type' => '',
    ])->assertSessionHasErrors(['account_type']);
});

it('completes step 2 and advances to details', function (): void {
    $user = User::factory()->unonboarded()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'onboarding_step' => 'account_type',
    ]);

    $response = $this->actingAs($user)->post(route('onboarding.account-type'), [
        'account_type' => 'business',
    ]);

    $response->assertRedirect();
    expect($user->fresh()->profile->account_type)->toBe('business')
        ->and($user->fresh()->profile->onboarding_step)->toBe('details');
});

it('enforces step order — cannot skip capabilities before account_type', function (): void {
    $user = User::factory()->unonboarded()->create();
    Profile::factory()->incomplete()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('onboarding.account-type'), [
        'account_type' => 'personal',
    ]);

    $response->assertRedirect(route('onboarding.index'));
});

it('validates step 3 — display_name, phone, primary_location required', function (): void {
    $user = User::factory()->unonboarded()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'account_type' => 'personal',
        'onboarding_step' => 'details',
    ]);

    $response = $this->actingAs($user)->post(route('onboarding.details'), [
        'display_name' => '',
        'phone' => '',
        'primary_location_id' => 999999,
    ]);

    $response->assertSessionHasErrors(['display_name', 'phone', 'primary_location_id']);
});

it('validates step 3 — business requires legal_name and legal_form', function (): void {
    $user = User::factory()->unonboarded()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'account_type' => 'business',
        'onboarding_step' => 'details',
    ]);

    $response = $this->actingAs($user)->post(route('onboarding.details'), [
        'display_name' => 'Abebe',
        'phone' => '+251 91 123 4567',
        'primary_location_id' => $loc->id,
        'legal_name' => '',
        'legal_form' => '',
    ]);

    $response->assertSessionHasErrors(['legal_name', 'legal_form']);
});

it('requires legal_form_other when legal_form is Other', function (): void {
    $user = User::factory()->unonboarded()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'account_type' => 'business',
        'onboarding_step' => 'details',
    ]);

    $response = $this->actingAs($user)->post(route('onboarding.details'), [
        'display_name' => 'Abebe',
        'phone' => '+251 91 123 4567',
        'primary_location_id' => $loc->id,
        'legal_name' => 'My Biz',
        'legal_form' => 'Other',
        'legal_form_other' => '',
    ]);

    $response->assertSessionHasErrors(['legal_form_other']);
});

it('completes step 3 for personal — marks profile completed and redirects to dashboard', function (): void {
    $user = User::factory()->unonboarded()->create();
    $loc = Location::factory()->create(['name' => 'Kombolcha', 'region_name' => 'Amhara']);
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'account_type' => 'personal',
        'onboarding_step' => 'details',
    ]);

    $response = $this->actingAs($user)->post(route('onboarding.details'), [
        'display_name' => 'Abebe Kebede',
        'phone' => '+251 91 123 4567',
        'primary_location_id' => $loc->id,
    ]);

    $response->assertRedirect(route('dashboard'));
    $profile = $user->fresh()->profile;
    expect($profile->onboarding_step)->toBe('completed')
        ->and($profile->onboarding_completed_at)->not->toBeNull()
        ->and($profile->display_name)->toBe('Abebe Kebede');
    expect($user->fresh()->onboarded_at)->not->toBeNull();
});

it('completes step 3 for business with Other legal form', function (): void {
    $user = User::factory()->unonboarded()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => true,
        'account_type' => 'business',
        'onboarding_step' => 'details',
    ]);

    $response = $this->actingAs($user)->post(route('onboarding.details'), [
        'display_name' => 'Abebe — ABC Transport',
        'phone' => '+251 91 123 4567',
        'primary_location_id' => $loc->id,
        'legal_name' => 'ABC Transport PLC',
        'legal_form' => 'Other',
        'legal_form_other' => 'Custom Cooperative Union',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->profile->legal_form_other)->toBe('Custom Cooperative Union');
});

it('prevents revisiting onboarding once completed — redirects to dashboard', function (): void {
    $user = User::factory()->create(); // onboarded via legacy flag
    Profile::factory()->create([
        'user_id' => $user->id,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($user)->get(route('onboarding.index'))->assertRedirect(route('dashboard'));
    $this->actingAs($user)->post(route('onboarding.capabilities'), [
        'posts_freight' => true,
        'provides_transport' => false,
    ])->assertRedirect(route('dashboard'));
});

it('middleware onboarded gates dashboard when profile incomplete', function (): void {
    $user = User::factory()->unonboarded()->create();
    Profile::factory()->incomplete()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('onboarding.index'));
});

it('allows onboarded profile to access dashboard', function (): void {
    $user = User::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);
    // Ensure workspace exists for dashboard middleware (workspace check)
    Workspace::factory()->create(['owner_id' => $user->id]);
    $user->update(['current_workspace_id' => Workspace::where('owner_id', $user->id)->first()->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    // Should not redirect to onboarding (may still redirect due to other middleware but not onboarding)
    expect($response->status())->not->toBe(302);
    // If redirected, ensure not to onboarding
    if ($response->isRedirect()) {
        expect($response->headers->get('Location'))->not->toContain('onboarding');
    }
});
