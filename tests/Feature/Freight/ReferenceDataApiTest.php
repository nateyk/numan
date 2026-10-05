<?php

use App\Models\CargoType;
use App\Models\Location;
use App\Models\TruckType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The public reference-data endpoints.
 *
 * Unauthenticated on purpose: a client building a freight form needs the cargo
 * and truck type lists and a town list before anybody has signed in. That makes
 * the response shape part of the public contract, and it makes it somewhere a
 * careless "just return the model" would leak a great deal.
 */
uses(RefreshDatabase::class);

it('serves cargo types without authentication', function () {
    CargoType::factory()->create(['name' => 'Cement', 'is_active' => true]);
    CargoType::factory()->create(['name' => 'Retired Type', 'is_active' => false]);

    $this->getJson('/api/cargo-types')
        ->assertOk()
        ->assertJsonStructure(['cargo_types' => [['id', 'name', 'slug', 'sort_order']]]);
});

it('omits an inactive cargo type', function () {
    CargoType::factory()->create(['name' => 'Active', 'is_active' => true]);
    CargoType::factory()->create(['name' => 'Hidden', 'is_active' => false]);

    $names = collect($this->getJson('/api/cargo-types')->json('cargo_types'))->pluck('name');

    expect($names)->toContain('Active')->not->toContain('Hidden');
});

it('serves truck types without authentication', function () {
    TruckType::factory()->create(['name' => 'Boveta', 'is_active' => true]);
    TruckType::factory()->create(['name' => 'Retired Truck', 'is_active' => false]);

    $response = $this->getJson('/api/truck-types')->assertOk();

    $response->assertJsonStructure(['truck_types' => [['id', 'name', 'slug', 'icon', 'description', 'sort_order']]]);

    $names = collect($response->json('truck_types'))->pluck('name');
    expect($names)->toContain('Boveta')->not->toContain('Retired Truck');
});

it('serves major towns without authentication', function () {
    Location::factory()->create(['name' => 'Adwa', 'is_major_town' => true]);
    Location::factory()->create(['name' => 'Small Village', 'is_major_town' => false]);

    $response = $this->getJson('/api/locations')->assertOk();

    $response->assertJsonStructure(['locations' => [['id', 'name', 'slug', 'region_name', 'type', 'latitude', 'longitude', 'population']]]);

    $names = collect($response->json('locations'))->pluck('name');
    expect($names)->toContain('Adwa')->not->toContain('Small Village');
});

it('returns an empty list rather than an error when there is no data', function () {
    // A fresh production database with no reference data must not 500 a client
    // that is building a form.
    $this->getJson('/api/cargo-types')->assertOk()->assertExactJson(['cargo_types' => []]);
    $this->getJson('/api/truck-types')->assertOk()->assertExactJson(['truck_types' => []]);
    $this->getJson('/api/locations')->assertOk()->assertExactJson(['locations' => []]);
});

it('exposes no column beyond the documented set', function () {
    // Explicit column lists, not ->get(). These are the three endpoints where
    // that matters most, because they are the only unauthenticated reads of
    // application tables in the application.
    CargoType::factory()->create();
    TruckType::factory()->create();
    Location::factory()->create(['is_major_town' => true]);

    $endpoints = [
        'cargo-types' => ['cargo_types', ['id', 'name', 'slug', 'sort_order']],
        'truck-types' => ['truck_types', ['id', 'name', 'slug', 'icon', 'description', 'sort_order']],
        'locations' => ['locations', ['id', 'name', 'slug', 'region_name', 'type', 'latitude', 'longitude', 'population']],
    ];

    foreach ($endpoints as $endpoint => [$key, $columns]) {
        $row = $this->getJson('/api/'.$endpoint)->json($key.'.0');

        expect($row)->not->toBeNull();
        expect(array_keys($row))->toEqual($columns);
    }
});

it('never returns a contact detail', function () {
    // Locations carry coordinates, which is the point. They must not carry
    // anything else, and nothing here is a person's details.
    $location = Location::factory()->create(['name' => 'Adwa', 'is_major_town' => true]);

    $body = $this->getJson('/api/locations')->content();

    foreach (['phone', 'email', 'whatsapp', 'contact_phone', 'legal_name', 'user_id', 'workspace_id'] as $forbidden) {
        expect($body)->not->toContain($forbidden);
    }

    expect($location->exists)->toBeTrue();
});

it('does not expose internal bookkeeping on locations', function () {
    Location::factory()->create(['is_major_town' => true]);

    $row = $this->getJson('/api/locations')->json('locations.0');

    // A town is reference data. osm_id and source are ours, not the caller's.
    expect($row)->not->toHaveKey('osm_id')
        ->and($row)->not->toHaveKey('source')
        ->and($row)->not->toHaveKey('is_active')
        ->and($row)->not->toHaveKey('created_at')
        ->and($row)->not->toHaveKey('updated_at');
});

it('orders the lists so the UI renders the same order every time', function () {
    CargoType::factory()->create(['name' => 'Zebra', 'sort_order' => 9, 'is_active' => true]);
    CargoType::factory()->create(['name' => 'Alpha', 'sort_order' => 1, 'is_active' => true]);

    $names = collect($this->getJson('/api/cargo-types')->json('cargo_types'))->pluck('name');

    expect($names->first())->toBe('Alpha');
});
