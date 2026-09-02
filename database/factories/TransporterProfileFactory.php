<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\TransporterProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TransporterProfile>
 */
class TransporterProfileFactory extends Factory
{
    protected $model = TransporterProfile::class;

    public function definition(): array
    {
        $displayName = fake()->company();

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($displayName).'-'.Str::random(4),
            'display_name' => $displayName,
            'legal_name' => fake()->company().' PLC',
            'phone' => '+251 91 '.fake()->numerify('### ####'),
            'whatsapp' => '+251 91 '.fake()->numerify('### ####'),
            'email' => fake()->safeEmail(),
            'business_description' => fake()->paragraph(),
            'is_company' => fake()->boolean(),
            'status' => 'draft',
            'verification_level' => 'unverified',
            'completion_score' => fake()->numberBetween(0, 100),
            'phone_visible' => fake()->boolean(),
            'whatsapp_visible' => fake()->boolean(),
            'email_visible' => fake()->boolean(),
            'primary_location_id' => Location::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'verification_level' => fake()->randomElement(['unverified', 'verified']),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'verification_level' => 'verified',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }
}
