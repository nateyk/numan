<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public profile page with the fields the form needs', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['provides_transport' => true]);

    $this->actingAs($user)
        ->get('/public-profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public-profile/index')
            ->has('profile')
            ->has('locations')
            ->has('truckTypes')
            ->has('accountTypes')
            ->has('legalForms')
            ->has('completionScore')
            ->has('profile.documents'));
});
