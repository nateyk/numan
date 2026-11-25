<?php

namespace Database\Seeders;

use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class FreightBrowserAcceptanceSeeder extends Seeder
{
    /**
     * Synthetic browser fixtures only. Never run on production or existing
     * customer data. The workflow creates a fresh, ephemeral SQLite database.
     */
    public function run(): void
    {
        if (! app()->environment('testing') || getenv('BROWSER_ACCEPTANCE_ENABLED') !== 'true') {
            throw new \RuntimeException('Browser acceptance fixtures require the isolated testing environment.');
        }

        $this->call(MarketplacePlanSeeder::class);

        $addis = Location::factory()->create(['name' => 'Browser Addis Ababa', 'region_name' => 'Addis Ababa']);
        $adama = Location::factory()->create(['name' => 'Browser Adama', 'region_name' => 'Oromia']);
        $hawassa = Location::factory()->create(['name' => 'Browser Hawassa', 'region_name' => 'Sidama']);
        $dire = Location::factory()->create(['name' => 'Browser Dire Dawa', 'region_name' => 'Dire Dawa']);

        $consignor = $this->createUser('consignor-browser@numan.invalid', 'Browser Test Consignor', '+251900111222', $addis, false);
        $transporter = $this->createUser('transporter-browser@numan.invalid', 'Browser Test Transporter', '+251900333444', $adama, true);
        $admin = $this->createUser('admin-browser@numan.invalid', 'Browser Test Admin', '+251900555666', $addis, false);
        $admin->update(['is_superadmin' => true]);

        // Multiple channels exercise both contact actions and de-duplication.
        // These numbers belong only to disposable browser-test accounts.
        $consignor->profile->update([
            'phone_numbers' => ['+251900111222', '+251900222333', '+251900111222'],
            'whatsapp' => '+251900444555',
            'whatsapp_numbers' => ['+251900444555', '0911777888'],
        ]);

        $common = [
            'user_id' => $consignor->id,
            'pickup_date' => now()->addDays(7)->toDateString(),
            'pickup_date_flexibility_days' => 2,
            'published_at' => now()->subDay(),
            'created_at' => now()->subDays(4),
        ];

        $contact = FreightPost::factory()->for($consignor)->active()->create([
            ...$common,
            'item_name' => 'Browser contact freight',
            'actual_weight' => '400',
            'weight_unit' => 'quintal',
            'tariff_weight' => '400',
            'tariff_weight_unit' => 'quintal',
            'origin_site_note' => 'Warehouse loading entrance',
            'destination_site_note' => 'Receiver gate',
            'origin_location_id' => $addis->id,
            'destination_location_id' => $adama->id,
        ]);

        // Keep browser rendering deterministic without an external OSRM request.
        $contact->routeSnapshot()->create([
            'distance_meters' => 95000,
            'duration_seconds' => 7200,
            'routing_provider' => 'synthetic',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [(float) $addis->longitude, (float) $addis->latitude],
                    [(float) $adama->longitude, (float) $adama->latitude],
                ],
            ],
        ]);

        $cancel = FreightPost::factory()->for($consignor)->active()->create([
            ...$common,
            'item_name' => 'Browser cancellation freight',
            'origin_location_id' => $addis->id,
            'destination_location_id' => $hawassa->id,
        ]);

        $arrive = FreightPost::factory()->for($consignor)->create([
            ...$common,
            'item_name' => 'Browser arrival freight',
            'origin_location_id' => $adama->id,
            'destination_location_id' => $hawassa->id,
            'status' => 'closed',
            'closed_at' => now()->subHours(5),
            'closure_reason' => 'matched',
        ]);

        $cancelled = FreightPost::factory()->for($consignor)->create([
            ...$common,
            'item_name' => 'Browser previously cancelled freight',
            'origin_location_id' => $hawassa->id,
            'destination_location_id' => $dire->id,
            'status' => 'closed',
            'closed_at' => now()->subHours(3),
            'closure_reason' => 'cancelled',
        ]);

        $folder = storage_path('app/browser-acceptance');
        File::ensureDirectoryExists($folder);
        File::put($folder.'/fixtures.json', json_encode([
            'contact_ulid' => $contact->ulid,
            'cancel_ulid' => $cancel->ulid,
            'arrive_ulid' => $arrive->ulid,
            'cancelled_ulid' => $cancelled->ulid,
            'consignor_email' => $consignor->email,
            'transporter_email' => $transporter->email,
            'admin_email' => $admin->email,
            'consignor_phone' => $consignor->profile->phone,
            'consignor_secondary_phone' => '+251900222333',
            'consignor_whatsapp' => '+251900444555',
            'consignor_secondary_whatsapp' => '0911777888',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function createUser(string $email, string $name, string $phone, Location $base, bool $carrier): User
    {
        $password = getenv('BROWSER_ACCEPTANCE_PASSWORD');

        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('A synthetic browser-test password is required.');
        }

        $user = User::factory()->withoutTwoFactor()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ]);

        app(WorkspaceService::class)->createPersonalWorkspace($user);

        Profile::factory()->create([
            'user_id' => $user->id,
            'display_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'primary_location_id' => $base->id,
            'posts_freight' => ! $carrier,
            'provides_transport' => $carrier,
            'account_type' => 'personal',
            'status' => $carrier ? 'approved' : null,
        ]);

        return $user->fresh();
    }
}
