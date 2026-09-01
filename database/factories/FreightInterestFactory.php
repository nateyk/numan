<?php

namespace Database\Factories;

use App\Enums\FreightInterestStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreightInterest>
 */
class FreightInterestFactory extends Factory
{
    protected $model = FreightInterest::class;

    public function definition(): array
    {
        return [
            'freight_post_id' => FreightPost::factory(),
            'user_id' => User::factory(),
            'status' => FreightInterestStatus::Submitted->value,
            'message' => fake()->sentence(),
        ];
    }
}
