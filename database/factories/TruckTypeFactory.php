<?php

namespace Database\Factories;

use App\Models\TruckType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TruckType>
 */
class TruckTypeFactory extends Factory
{
    protected $model = TruckType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(4),
            'is_active' => true,
            'icon' => 'truck',
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
