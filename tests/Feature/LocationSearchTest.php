<?php

use App\Models\Location;
use App\Models\User;

it('returns locations wrapper with slug', function () {
    Location::factory()->create(['name' => 'Addis Ababa', 'region_name' => 'Addis Ababa', 'slug' => 'addis-ababa-addis-ababa', 'is_active' => true]);
    $this->actingAs(User::factory()->create())->getJson('/locations/search?q=addis')->assertOk()->assertJsonStructure(['locations' => [['id', 'name', 'region_name', 'slug', 'latitude', 'longitude']]]);
});

it('returns empty wrapper when query empty', function () {
    $this->actingAs(User::factory()->create())->getJson('/locations/search?q=')->assertOk()->assertJson(['locations' => []]);
});
