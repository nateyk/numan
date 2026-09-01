<?php

use App\Enums\FreightPostStatus;
use App\Models\CargoType;
use App\Models\FreightPost;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only lists active freight publicly', function () {
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);
    FreightPost::factory()->create(['status' => FreightPostStatus::Draft->value]);

    $this->get('/freight')->assertOk()->assertInertia(fn ($page) => $page->component('discover/index')->where('freight.data', fn ($d) => count($d) === 1));
});

it('filters by origin and cargo type', function () {
    $loc1 = Location::factory()->create();
    $loc2 = Location::factory()->create();
    $cargo1 = CargoType::factory()->create();
    $cargo2 = CargoType::factory()->create();

    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'origin_location_id' => $loc1->id, 'cargo_type_id' => $cargo1->id]);
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'origin_location_id' => $loc2->id, 'cargo_type_id' => $cargo2->id]);

    $this->get('/freight?origin=' . $loc1->id)->assertOk()->assertInertia(fn ($page) => $page->where('freight.data', fn ($d) => count($d) === 1));
    $this->get('/freight?cargo_type=' . $cargo1->id)->assertOk()->assertInertia(fn ($page) => $page->where('freight.data', fn ($d) => count($d) === 1));
});

it('is accessible via discover route as well', function () {
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);
    $this->get('/discover')->assertOk()->assertInertia(fn ($page) => $page->component('discover/index'));
});
