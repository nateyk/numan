<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        $accountType = fake()->randomElement(['personal', 'business']);
        $isBusiness = $accountType === 'business';

        return [
            'user_id' => User::factory(),
            'posts_freight' => fake()->boolean(),
            'provides_transport' => fake()->boolean(),
            'account_type' => $accountType,
            'display_name' => fake()->name(),
            'phone' => '+251 91 '.fake()->numerify('### ####'),
            'primary_location_id' => Location::factory(),
            'legal_name' => $isBusiness ? fake()->company().' PLC' : null,
            'legal_form' => $isBusiness ? fake()->randomElement(['Private Limited Company (PLC)', 'Share Company (SC)', 'Sole Proprietorship', 'Other']) : null,
            'legal_form_other' => null,
            'onboarding_step' => 'completed',
            'onboarding_completed_at' => now(),
        ];
    }

    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'posts_freight' => false,
            'provides_transport' => false,
            'account_type' => null,
            'display_name' => null,
            'phone' => null,
            'primary_location_id' => null,
            'legal_name' => null,
            'legal_form' => null,
            'onboarding_step' => 'capabilities',
            'onboarding_completed_at' => null,
        ]);
    }

    public function capabilities(): static
    {
        return $this->state(fn (array $attributes) => [
            'posts_freight' => true,
            'provides_transport' => false,
            'onboarding_step' => 'account_type',
            'onboarding_completed_at' => null,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'business',
            'legal_name' => fake()->company().' PLC',
            'legal_form' => 'Private Limited Company (PLC)',
            'onboarding_step' => 'details',
        ]);
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'personal',
            'legal_name' => null,
            'legal_form' => null,
            'onboarding_step' => 'details',
        ]);
    }
}
