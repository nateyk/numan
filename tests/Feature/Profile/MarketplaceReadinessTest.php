<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MarketplaceReadiness;

it('reports a profile-only carrier ready to express interest', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'display_name' => 'Carrier',
        'phone' => '+251911000001',
        'primary_location_id' => Location::factory()->create()->id,
        'posts_freight' => false,
        'provides_transport' => true,
    ]);

    $state = app(MarketplaceReadiness::class)->for($user);

    expect($state['profile_exists'])->toBeTrue()
        ->and($state['ready']['provides_transport'])->toBeTrue()
        ->and($state['ready']['posts_freight'])->toBeFalse();
});

it('lets a profile-only carrier express interest', function () {
    $consignor = User::factory()->create();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    $carrier = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $carrier->id]);
    $workspace->users()->attach($carrier->id, ['role' => 'owner']);
    $carrier->update(['current_workspace_id' => $workspace->id]);

    Profile::factory()->for($carrier)->create([
        'display_name' => 'Carrier',
        'phone' => '+251911000001',
        'primary_location_id' => Location::factory()->create()->id,
        'provides_transport' => true,
    ]);

    $this->actingAs($carrier)
        ->post(route('freight.interests.store', $post->ulid))
        ->assertRedirect();

    expect(FreightInterest::where('user_id', $carrier->id)->exists())->toBeTrue();
});
