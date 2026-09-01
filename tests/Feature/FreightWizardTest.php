<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingAsOnboardedUserWithCapability(string $capability): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => $capability === 'posts_freight',
        'provides_transport' => $capability === 'provides_transport',
        'account_type' => 'personal',
        'display_name' => 'Test User',
        'phone' => '+251900000000',
        'primary_location_id' => $location->id,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

it('creates draft and allows stepwise update', function () {
    $user = actingAsOnboardedUserWithCapability('posts_freight');
    $origin = Location::factory()->create();
    $dest = Location::factory()->create();

    $res = $this->actingAs($user)->post('/my-freight');
    $res->assertRedirect();
    $ulid = FreightPost::first()->ulid;
    expect($ulid)->not->toBeNull();

    $this->actingAs($user)->put("/my-freight/{$ulid}/route", [
        'origin_location_id' => $origin->id,
        'destination_location_id' => $dest->id,
        'origin_exact_lat' => 9.03,
        'origin_exact_lng' => 38.74,
        'destination_exact_lat' => 7.49,
        'destination_exact_lng' => 38.42,
        'origin_point_confirmed_at' => now()->toDateTimeString(),
        'destination_point_confirmed_at' => now()->toDateTimeString(),
        'pickup_date' => now()->addDays(2)->toDateString(),
    ])->assertRedirect();

    expect(FreightPost::first()->origin_location_id)->not->toBeNull();
});

it('rejects submit when incomplete', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $location->id,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = FreightPost::factory()->create([
        'user_id' => $user->id,
        'status' => FreightPostStatus::Draft->value,
        'cargo_type_id' => null,
        'cargo_description' => null,
    ]);

    $this->actingAs($user->fresh())->post("/my-freight/{$post->ulid}/submit")->assertSessionHasErrors();
});
