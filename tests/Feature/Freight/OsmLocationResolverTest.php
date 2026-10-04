<?php

use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A picked OpenStreetMap result becomes a real locations row.
 *
 * Without this the freight route step is unsaveable for anyone who picks a place
 * that only exists in OSM, which is most of Ethiopia and Djibouti, because
 * origin_location_id is a foreign key and an "osm_1234" is not one.
 */
uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function osmPick(array $overrides = []): array
{
    return array_merge([
        'osm_type' => 'relation',
        'osm_id' => 987654,
        'name' => 'Adwa',
        'type' => 'town',
        'region_name' => 'Amhara',
        'latitude' => 9.03,
        'longitude' => 38.74,
        'is_major_town' => true,
    ], $overrides);
}

/**
 * A freight-poster with a draft post.
 *
 * @return array{0: User, 1: string}
 */
function osmRouteUser(): array
{
    $user = User::factory()->create();
    $location = Location::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
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

    test()->actingAs($user->fresh())->post('/my-freight');

    return [$user->fresh(), FreightPost::firstOrFail()->ulid];
}

it('creates a locations row from a picked OSM result', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick());

    $response->assertOk();

    $location = Location::where('osm_id', '987654')->firstOrFail();

    expect($location->name)->toBe('Adwa')
        ->and($location->region_name)->toBe('Amhara')
        ->and($location->source)->toBe('osm')
        ->and($location->is_active)->toBeTrue()
        ->and((float) $location->latitude)->toBe(9.03);
});

it('reuses the same row when the same feature is picked twice', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick());
    $second = $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick());

    $first->assertOk();
    $second->assertOk();

    // Nominatim returns the same feature for the same query forever, so without
    // the osm_id key every search would insert another copy of the same town.
    expect($second->json('location.id'))->toBe($first->json('location.id'))
        ->and(Location::where('osm_id', '987654')->count())->toBe(1);
});

it('gives two places with the same name different rows', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick(['osm_id' => 111]));
    $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick([
        'osm_id' => 222,
        'name' => 'Adwa',
        'region_name' => 'Oromia',
    ]));

    // Identity is the OSM feature id, never the name. Keying on the name would
    // merge two genuinely different towns.
    expect(Location::where('osm_id', '!=', null)->count())->toBe(2);
});

it('gives two places with the same name different slugs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick(['osm_id' => 111]));
    $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick(['osm_id' => 222]));

    expect(Location::where('osm_id', '!=', null)->pluck('slug')->unique())->toHaveCount(2);
});

it('lets an OSM pick be saved as a real route', function () {
    [$user, $ulid] = osmRouteUser();

    $pick = $this->actingAs($user)->postJson('/locations/resolve-osm', [
        'osm_type' => 'relation',
        'osm_id' => 555001,
        'name' => 'Adwa',
        'type' => 'town',
        'region_name' => 'Amhara',
        'latitude' => 9.03,
        'longitude' => 38.74,
    ]);

    $originId = $pick->json('location.id');

    $destination = Location::factory()->create();

    $this->actingAs($user)
        ->put("/my-freight/{$ulid}/route", [
            'origin_location_id' => $originId,
            'destination_location_id' => $destination->id,
            'origin_exact_lat' => 9.03,
            'origin_exact_lng' => 38.74,
            'destination_exact_lat' => 7.49,
            'destination_exact_lng' => 38.42,
            'origin_point_confirmed_at' => now()->toDateTimeString(),
            'destination_point_confirmed_at' => now()->toDateTimeString(),
            'schedule_type' => 'one_time',
            'pickup_date' => now()->addDays(3)->toDateString(),
        ])
        ->assertRedirect();

    $post = FreightPost::where('ulid', $ulid)->firstOrFail();

    // The foreign key points at a row that exists, which is the whole point.
    expect((int) $post->origin_location_id)->toBe($originId)
        ->and(Location::find($originId))->not->toBeNull();
});

it('requires authentication', function () {
    $this->postJson('/locations/resolve-osm', osmPick())->assertUnauthorized();

    expect(Location::where('osm_id', '!=', null)->count())->toBe(0);
});

it('rejects coordinates outside the world', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/locations/resolve-osm', osmPick(['latitude' => 120]))
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson('/locations/resolve-osm', osmPick(['longitude' => 400]))
        ->assertStatus(422);
});

it('rejects a pick with no name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/locations/resolve-osm', osmPick(['name' => '']))
        ->assertStatus(422);
});

it('refuses a pick with no osm id, because it cannot be deduplicated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/locations/resolve-osm', osmPick(['osm_id' => null]))
        ->assertStatus(422);
});

it('is throttled', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($user)->postJson('/locations/resolve-osm', osmPick(['osm_id' => 1000 + $i]));
    }

    $this->actingAs($user)
        ->postJson('/locations/resolve-osm', osmPick(['osm_id' => 9999]))
        ->assertStatus(429);
});
