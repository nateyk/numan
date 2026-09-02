<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $regions = ['Addis Ababa', 'Amhara', 'Oromia', 'Tigray', 'Sidama', 'Dire Dawa', 'Harari', 'Somali'];

        return [
            'name' => fake()->unique()->city(),
            'type' => fake()->randomElement(['city', 'town']),
            'region_name' => fake()->randomElement($regions),
            'latitude' => fake()->latitude(3, 15),
            'longitude' => fake()->longitude(33, 48),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
