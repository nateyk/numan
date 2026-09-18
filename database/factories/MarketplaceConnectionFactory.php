<?php

namespace Database\Factories;

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplaceConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketplaceConnection>
 */
class MarketplaceConnectionFactory extends Factory
{
    protected $model = MarketplaceConnection::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'freight_post_id' => FreightPost::factory(),
            'freight_interest_id' => FreightInterest::factory(),
            'consignor_user_id' => User::factory(),
            'transporter_user_id' => User::factory(),
        ];
    }
}
