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
            'posts_freight' => true,
            'provides_transport' => false,
            'account_type' => $accountType,
            'display_name' => fake()->name(),
            'phone' => '+251 91 '.fake()->numerify('### ####'),
            'email' => fake()->safeEmail(),
            'business_description' => $isBusiness ? fake()->paragraph() : null,
            'primary_location_id' => Location::factory(),
            'legal_name' => $isBusiness ? fake()->company().' PLC' : null,
            'legal_form' => $isBusiness ? fake()->randomElement(['Private Limited Company (PLC)', 'Share Company (SC)', 'Sole Proprietorship', 'Other']) : null,
            'legal_form_other' => null,
            'status' => null,
            'verification_status' => 'unverified',
            'completion_score' => 0,
            'phone_visible' => true,
            'whatsapp_visible' => true,
            'email_visible' => true,
            'onboarding_step' => 'completed',
            'onboarding_completed_at' => now(),
        ];
    }

    public function consignor(): static
    {
        return $this->state(fn (array $attributes) => [
            'posts_freight' => true,
            'provides_transport' => false,
            'status' => null,
        ]);
    }

    public function transporter(): static
    {
        return $this->state(fn (array $attributes) => [
            'posts_freight' => false,
            'provides_transport' => true,
            'status' => 'draft',
        ]);
    }

    public function both(): static
    {
        return $this->state(fn (array $attributes) => [
            'posts_freight' => true,
            'provides_transport' => true,
            'status' => 'draft',
        ]);
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
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'verified',
        ]);
    }

    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'posts_freight' => false,
            'provides_transport' => false,
            'account_type' => null,
            'display_name' => null,
            'phone' => null,
            'email' => null,
            'business_description' => null,
            'primary_location_id' => null,
            'legal_name' => null,
            'legal_form' => null,
            'status' => null,
            'verification_status' => 'unverified',
            'completion_score' => 0,
            'onboarding_step' => 'capabilities',
            'onboarding_completed_at' => null,
        ]);
    }

    public function capabilities(): static
    {
        return $this->state(fn (array $attributes) => [
            'posts_freight' => true,
            'provides_transport' => false,
            'status' => null,
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
