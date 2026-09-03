<?php

use App\Models\Location;
use Database\Seeders\EthiopiaMajorTownsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds 934 major towns idempotently', function () {
    $this->seed(EthiopiaMajorTownsSeeder::class);
    expect(Location::where('is_major_town', true)->count())->toBe(934);

    // Re-run should not duplicate
    $this->seed(EthiopiaMajorTownsSeeder::class);
    expect(Location::where('is_major_town', true)->count())->toBe(934);
});

it('searches towns by name and region', function () {
    $this->seed(EthiopiaMajorTownsSeeder::class);

    $addis = Location::search('Addis')->first();
    expect($addis)->not->toBeNull()
        ->and($addis->name)->toBe('Addis Abeba');

    $tigray = Location::search('TIGRAY')->count();
    expect($tigray)->toBeGreaterThan(10);
});

it('stores elevation and population for towns', function () {
    $this->seed(EthiopiaMajorTownsSeeder::class);

    $town = Location::where('name', 'Addis Abeba')->first();
    expect($town)->not->toBeNull()
        ->and($town->elevation)->not->toBeNull()
        ->and($town->population)->not->toBeNull()
        ->and($town->wereda)->not->toBeNull();
});
