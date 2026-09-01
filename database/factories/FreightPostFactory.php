<?php

namespace Database\Factories;

use App\Enums\FreightPostStatus;
use App\Enums\ScheduleType;
use App\Models\CargoType;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\TruckType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FreightPost>
 */
class FreightPostFactory extends Factory
{
    protected $model = FreightPost::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'origin_location_id' => Location::factory(),
            'destination_location_id' => Location::factory(),
            'origin_exact_lat' => fake()->latitude(3, 15),
            'origin_exact_lng' => fake()->longitude(33, 48),
            'destination_exact_lat' => fake()->latitude(3, 15),
            'destination_exact_lng' => fake()->longitude(33, 48),
            'origin_site_notes' => fake()->optional()->sentence(),
            'destination_site_notes' => fake()->optional()->sentence(),
            'origin_site_note' => fake()->optional()->sentence(),
            'destination_site_note' => fake()->optional()->sentence(),
            'origin_point_confirmed_at' => now(),
            'destination_point_confirmed_at' => now(),
            'cargo_type_id' => CargoType::factory(),
            'cargo_description' => fake()->sentence(),
            'required_truck_type_id' => TruckType::factory(),
            'truck_count' => fake()->numberBetween(1, 5),
            'schedule_type' => ScheduleType::OneTime->value,
            'pickup_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'pickup_time' => fake()->time('H:i:s'),
            'pickup_date_flexibility_days' => fake()->numberBetween(0, 5),
            'status' => FreightPostStatus::Draft->value,
            'completion_score' => fake()->numberBetween(0, 100),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FreightPostStatus::Draft->value,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FreightPostStatus::Submitted->value,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FreightPostStatus::Active->value,
        ]);
    }
}
