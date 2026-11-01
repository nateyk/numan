<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TruckType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates the profile on first visit and shows the management page', function () {
    // The React page arrives in Task 5; the manifest does not know it yet.
    $this->withoutVite();

    $user = User::factory()->create(['name' => 'Ada Consignor']);

    $this->actingAs($user)
        ->get('/public-profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public-profile/index'));

    expect($user->profile()->first()->display_name)->toBe('Ada Consignor');
});

it('requires at least one capability when saving', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', [
            'display_name' => 'No Capability',
            'account_type' => 'personal',
            'posts_freight' => false,
            'provides_transport' => false,
        ])
        ->assertSessionHasErrors('capabilities');
});

it('saves transport details and submits them for review', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['provides_transport' => true, 'status' => 'draft']);
    $truckType = TruckType::factory()->create();
    $origin = Location::factory()->create();
    $destination = Location::factory()->create();

    $this->actingAs($user)
        ->put('/public-profile/transport', [
            'truck_type_ids' => [$truckType->id],
            'routes' => [[
                'origin_location_id' => $origin->id,
                'destination_location_id' => $destination->id,
            ]],
        ])
        ->assertRedirect();

    $profile = $user->profile()->first()->refresh();

    expect($profile->status)->toBe('pending')
        ->and($profile->truckTypes()->count())->toBe(1)
        ->and($profile->routes()->count())->toBe(1);
});

it('rejects transport details when transport is turned off', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['provides_transport' => false]);

    $this->actingAs($user)
        ->put('/public-profile/transport', [
            'truck_type_ids' => [TruckType::factory()->create()->id],
        ])
        ->assertSessionHasErrors('transport');

    expect($user->profile->fresh()->truckTypes()->count())->toBe(0);
});

it('rejects duplicate routes without changing saved carrier details', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['provides_transport' => true, 'status' => 'draft']);
    $origin = Location::factory()->create();
    $destination = Location::factory()->create();
    $existingDestination = Location::factory()->create();
    $existingTruck = TruckType::factory()->create();
    $newTruck = TruckType::factory()->create();
    $profile->truckTypes()->attach($existingTruck);
    $profile->routes()->create(['origin_location_id' => $origin->id, 'destination_location_id' => $existingDestination->id]);

    $route = ['origin_location_id' => $origin->id, 'destination_location_id' => $destination->id];

    $this->actingAs($user)->put('/public-profile/transport', [
        'truck_type_ids' => [$newTruck->id],
        'routes' => [$route, $route],
    ])->assertSessionHasErrors('routes.1.destination_location_id');

    expect($profile->fresh()->truckTypes()->pluck('truck_types.id')->all())->toBe([$existingTruck->id])
        ->and($profile->routes()->pluck('destination_location_id')->all())->toBe([$existingDestination->id])
        ->and($profile->fresh()->status)->toBe('draft');
});

it('stores, downloads, and deletes an owned private document', function () {
    Storage::fake('private');
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)->post('/public-profile/documents', [
        'type' => 'license',
        'file' => UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $document = $user->profile->documents()->firstOrFail();

    $this->actingAs($user)
        ->get("/public-profile/documents/{$document->id}/download")
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get("/public-profile/documents/{$document->id}/download")
        ->assertForbidden();

    $this->actingAs($user)
        ->delete("/public-profile/documents/{$document->id}")
        ->assertRedirect();

    expect($user->profile->documents()->count())->toBe(0);
});

it('requests verification and stores the pending status', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'account_type' => 'personal',
        'display_name' => 'Carrier',
        'phone' => '+251911000001',
        'verification_status' => 'unverified',
    ]);

    $this->actingAs($user)
        ->post('/public-profile/verification')
        ->assertRedirect();

    expect($user->profile->fresh()->verification_status)->toBe('pending')
        ->and($user->profile->fresh()->verification_requested_at)->not->toBeNull();
});
