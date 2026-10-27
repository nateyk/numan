<?php

use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows owner to view route step for active post (no 403)', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);

    // Create profile for consignor
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'primary_location_id' => $loc->id,
    ]);

    $post = FreightPost::factory()->for($user)->create(['status' => 'active']);

    $this->actingAs($user)->get("/my-freight/{$post->ulid}/route")->assertOk();
    $this->actingAs($user)->get("/my-freight/{$post->ulid}/cargo")->assertOk();
    $this->actingAs($user)->get("/my-freight/{$post->ulid}/schedule")->assertOk();
});

it('prevents updating route for active post', function () {
    $user = User::factory()->create();
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'primary_location_id' => $loc->id,
    ]);
    $loc2 = Location::factory()->create();
    $post = FreightPost::factory()->for($user)->create(['status' => 'active']);

    $this->actingAs($user)->put("/my-freight/{$post->ulid}/route", [
        'origin_location_id' => $loc->id,
        'destination_location_id' => $loc2->id,
        'origin_exact_lat' => '9.03',
        'origin_exact_lng' => '38.74',
        'destination_exact_lat' => '7.05',
        'destination_exact_lng' => '38.47',
    ])->assertForbidden();
});
