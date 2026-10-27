<?php

use App\Models\FreightPost;
use App\Models\Profile;
use App\Models\User;

it('marks setup as required for a user who cannot post yet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/freight')
        ->assertInertia(fn ($page) => $page->where('profile_setup.required', true));
});

it('does not require setup for a ready consignor', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'display_name' => 'Ready',
        'phone' => '+251911000001',
        'posts_freight' => true,
    ]);

    $this->actingAs($user)->get('/freight')
        ->assertInertia(fn ($page) => $page->where('profile_setup.required', false));
});

it('saves the dialog fields without creating a freight draft', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/public-profile/setup', [
        'display_name' => 'Ada',
        'account_type' => 'personal',
        'phone' => '+251911000001',
        'provides_transport' => false,
    ]);
    $response->assertSessionHasNoErrors()->assertRedirect();

    expect($user->fresh()->profile->posts_freight)->toBeTrue()
        ->and(FreightPost::where('user_id', $user->id)->count())->toBe(0);
});

it('creates the draft after setup when the button retries', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'display_name' => 'Ada',
        'phone' => '+251911000001',
        'posts_freight' => true,
    ]);

    $this->actingAs($user)->post('/my-freight')->assertRedirect();

    expect(FreightPost::where('user_id', $user->id)->count())->toBe(1);
});
