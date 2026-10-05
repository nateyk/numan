<?php

namespace Database\Seeders;

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Enums\TransporterStatus;
use App\Enums\VerificationLevel;
use App\Models\CargoType;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\TransporterProfile;
use App\Models\TruckType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    /**
     * Refuse to run against production.
     *
     * This seeder creates demo accounts whose password is published in the
     * repository, including a superadmin. Running it on a live site is the one
     * mistake that hands over the whole platform, and it is a one-word mistake:
     * db:seed with no --class runs everything.
     *
     * Aborts rather than warns, because a warning nobody reads is the same as no
     * guard at all. Reference data is not lost by this -- run the individual
     * seeders instead, which are listed in docs/production-runbook.md.
     */
    private function guardAgainstProduction(string $seeder): void
    {
        if (! app()->environment('production')) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run %s in production. It creates demo accounts with a published password,'
            .' including a superadmin. To load reference data run the individual seeders'
            .' instead, e.g. "php artisan db:seed --class=LocationSeeder --force".',
            $seeder
        ));
    }

    public function run(): void
    {
        $this->guardAgainstProduction('DemoDataSeeder');

        // This seeder should be run AFTER the main DatabaseSeeder has seeded
        // the reference data (locations, cargo types, truck types, etc.)

        DB::transaction(function () {
            // 1. Create core users
            $admin = User::firstOrCreate(
                ['email' => 'admin@numanfreight.com'],
                [
                    'name' => 'Admin User',
                    'password' => DemoPassword::hashed(),
                    'email_verified_at' => now(),
                    'locale' => 'en',
                    'onboarded_at' => now(),
                ]
            );

            $demo = User::firstOrCreate(
                ['email' => 'demo@numanfreight.com'],
                [
                    'name' => 'Demo User',
                    'password' => DemoPassword::hashed(),
                    'email_verified_at' => now(),
                    'locale' => 'en',
                    'onboarded_at' => now(),
                ]
            );

            $superadmin = User::firstOrCreate(
                ['email' => 'superadmin@numanfreight.com'],
                [
                    'name' => 'Superadmin',
                    'password' => DemoPassword::hashed(),
                    'email_verified_at' => now(),
                    'locale' => 'en',
                    'is_superadmin' => true,
                    'onboarded_at' => now(),
                ]
            );

            // 2. Create additional demo users for testing
            $additionalUsers = User::factory(10)->create([
                'password' => DemoPassword::hashed(),
            ]);

            // 3. Get reference data (already seeded by main DatabaseSeeder)
            $locations = Location::active()->get();
            $cargoTypes = CargoType::active()->ordered()->get();
            $truckTypes = TruckType::active()->ordered()->get();

            // 4. Create demo workspaces
            $workspaces = collect();

            $workspaceNames = [
                'Acme Corporation',
                'Tech Startup Inc',
                'Digital Solutions LLC',
                'Creative Agency',
                'Global Enterprises',
            ];

            foreach ($workspaceNames as $index => $name) {
                $workspace = Workspace::firstOrCreate(
                    ['name' => $name, 'owner_id' => $demo->id],
                    [
                        'slug' => Str::slug($name).'-'.Str::random(4),
                        'personal_workspace' => false,
                    ]
                );

                // Set different plan scenarios for demo
                match ($index) {
                    0 => $workspace->update(['trial_ends_at' => now()->addDays(14)]), // Free plan
                    1 => $workspace->update(['trial_ends_at' => now()->addDays(30)]), // Pro trial
                    2 => $workspace->update(['trial_ends_at' => now()->addDays(90)]), // Business
                    default => $workspace->update(['trial_ends_at' => now()->addDays(7)]),
                };

                $workspaces->push($workspace);
            }

            // 5. Create demo freight posts with various statuses
            $locations = Location::active()->get();
            $cargoTypes = CargoType::active()->get();
            $truckTypes = TruckType::active()->get();

            $freightPosts = [
                // Active freight posts
                [
                    'user_id' => $demo->id,
                    'workspace_id' => $workspaces->first()?->id,
                    'origin_location_id' => $locations->where('name', 'Addis Ababa')->first()?->id,
                    'destination_location_id' => $locations->where('name', 'Hawassa')->first()?->id,
                    'origin_exact_lat' => 9.03,
                    'origin_exact_lng' => 38.74,
                    'destination_exact_lat' => 7.05,
                    'destination_exact_lng' => 38.47,
                    'cargo_type_id' => $cargoTypes->where('name', 'General')->first()?->id,
                    'cargo_description' => 'General cargo - mixed goods',
                    'item_name' => 'Mixed goods',
                    'actual_weight' => '15.5',
                    'weight_unit' => 'ton',
                    'tariff_amount' => '12500',
                    'tariff_currency' => 'ETB',
                    'required_truck_type_id' => $truckTypes->where('name', 'Flatbed')->first()?->id,
                    'truck_count' => 2,
                    'schedule_type' => 'one_time',
                    'pickup_date' => now()->addDays(3)->toDateString(),
                    'pickup_time' => '08:00',
                    'pickup_date_flexibility_days' => 2,
                    'status' => FreightPostStatus::Active->value,
                    'completion_score' => 100,
                ],
                [
                    'user_id' => $demo->id,
                    'workspace_id' => $workspaces->first()?->id,
                    'origin_location_id' => $locations->where('name', 'Dire Dawa')->first()?->id,
                    'destination_location_id' => $locations->where('name', 'Jijiga')->first()?->id,
                    'origin_exact_lat' => 9.59,
                    'origin_exact_lng' => 41.87,
                    'destination_exact_lat' => 9.35,
                    'destination_exact_lng' => 42.80,
                    'cargo_type_id' => $cargoTypes->where('name', 'Cereals')->first()?->id,
                    'cargo_description' => '20 tons of wheat in 50kg bags',
                    'item_name' => 'Wheat',
                    'actual_weight' => '20',
                    'weight_unit' => 'ton',
                    'tariff_amount' => '18000',
                    'tariff_currency' => 'ETB',
                    'required_truck_type_id' => $truckTypes->where('name', 'Dump Truck')->first()?->id,
                    'truck_count' => 1,
                    'schedule_type' => 'recurring',
                    'pickup_date' => now()->addDays(1)->toDateString(),
                    'pickup_time' => '06:00',
                    'pickup_date_flexibility_days' => 1,
                    'status' => FreightPostStatus::Active->value,
                    'completion_score' => 100,
                ],
                [
                    'user_id' => $demo->id,
                    'workspace_id' => $workspaces->first()?->id,
                    'origin_location_id' => $locations->where('name', 'Mekelle')->first()?->id,
                    'destination_location_id' => $locations->where('name', 'Shire')->first()?->id,
                    'origin_exact_lat' => 13.50,
                    'origin_exact_lng' => 39.47,
                    'destination_exact_lat' => 14.10,
                    'destination_exact_lng' => 38.28,
                    'cargo_type_id' => $cargoTypes->where('name', 'Construction')->first()?->id,
                    'cargo_description' => 'Cement bags - 500 bags',
                    'item_name' => 'Cement',
                    'actual_weight' => '25',
                    'weight_unit' => 'ton',
                    'tariff_amount' => '22000',
                    'tariff_currency' => 'ETB',
                    'required_truck_type_id' => $truckTypes->where('name', 'Tanker')->first()?->id,
                    'truck_count' => 3,
                    'schedule_type' => 'one_time',
                    'pickup_date' => now()->addDays(5)->toDateString(),
                    'pickup_time' => '10:00',
                    'pickup_date_flexibility_days' => 3,
                    'status' => FreightPostStatus::Submitted->value,
                    'completion_score' => 85,
                ],
                // Draft posts
                [
                    'user_id' => $demo->id,
                    'workspace_id' => $workspaces->first()?->id,
                    'origin_location_id' => $locations->where('name', 'Gondar')->first()?->id,
                    'destination_location_id' => $locations->where('name', 'Bahir Dar')->first()?->id,
                    'cargo_type_id' => $cargoTypes->where('name', 'General')->first()?->id,
                    'cargo_description' => 'Draft - furniture shipment',
                    'item_name' => 'Furniture',
                    'actual_weight' => '5',
                    'weight_unit' => 'ton',
                    'tariff_amount' => '8000',
                    'tariff_currency' => 'ETB',
                    'required_truck_type_id' => $truckTypes->where('name', 'Flatbed')->first()?->id,
                    'truck_count' => 1,
                    'schedule_type' => 'one_time',
                    'pickup_date' => now()->addDays(7)->toDateString(),
                    'pickup_time' => '09:00',
                    'status' => FreightPostStatus::Draft->value,
                    'completion_score' => 45,
                ],
                [
                    'user_id' => $demo->id,
                    'workspace_id' => $workspaces->first()?->id,
                    'origin_location_id' => $locations->where('name', 'Adama')->first()?->id,
                    'destination_location_id' => $locations->where('name', 'Asella')->first()?->id,
                    'cargo_type_id' => $cargoTypes->where('name', 'Refrigerated')->first()?->id,
                    'cargo_description' => 'Perishable goods - vegetables',
                    'item_name' => 'Vegetables',
                    'actual_weight' => '8',
                    'weight_unit' => 'ton',
                    'tariff_amount' => '15000',
                    'tariff_currency' => 'ETB',
                    'required_truck_type_id' => $truckTypes->where('name', 'Refrigerated Truck')->first()?->id,
                    'truck_count' => 2,
                    'status' => FreightPostStatus::Draft->value,
                    'completion_score' => 60,
                ],
            ];

            foreach ($freightPosts as $postData) {
                FreightPost::firstOrCreate(
                    ['ulid' => (string) Str::ulid()],
                    $postData
                );
            }

            // 6. Create demo interests
            $freightPosts = FreightPost::all();
            $interestUsers = User::where('id', '!=', $demo->id)->get();

            foreach ($freightPosts as $post) {
                // Add 1-3 interests per post
                $interestCount = rand(1, 3);
                $interestedUsers = $interestUsers->shuffle()->take($interestCount);

                foreach ($interestedUsers as $index => $user) {
                    $statuses = [
                        FreightInterestStatus::Submitted->value,
                        FreightInterestStatus::Shortlisted->value,
                        FreightInterestStatus::Selected->value,
                    ];

                    FreightInterest::firstOrCreate(
                        ['freight_post_id' => $post->id, 'user_id' => $user->id],
                        [
                            'status' => $statuses[$index % count($statuses)],
                            'message' => 'I am interested in transporting this cargo. Available truck and driver.',
                            'contact_name' => $user->name,
                            'contact_phone' => '+251 91 '.fake()->numerify('### ####'),
                            'contact_email' => $user->email,
                        ]
                    );
                }
            }

            // 7. Create demo transporter profiles
            $transporterUsers = User::where('id', '!=', $demo->id)->take(5)->get();

            foreach ($transporterUsers as $index => $user) {
                $statuses = [
                    TransporterStatus::Approved->value,
                    TransporterStatus::Pending->value,
                ];

                $verificationLevels = [
                    VerificationLevel::Verified->value,
                    VerificationLevel::Unverified->value,
                ];

                $status = $statuses[$index % count($statuses)];
                $verification = $verificationLevels[$index % count($verificationLevels)];

                TransporterProfile::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'slug' => Str::slug($user->name).'-'.Str::random(4),
                        'display_name' => $user->name.' Transport',
                        'legal_name' => $user->name.' Transport PLC',
                        'phone' => '+251 91 '.fake()->numerify('### ####'),
                        'whatsapp' => '+251 91 '.fake()->numerify('### ####'),
                        'email' => $user->email,
                        'business_description' => 'Reliable transport services across Ethiopia. Modern fleet with GPS tracking.',
                        'is_company' => true,
                        'status' => $status,
                        'verification_level' => $verification,
                        'completion_score' => rand(70, 95),
                        'phone_visible' => true,
                        'whatsapp_visible' => true,
                        'email_visible' => false,
                        'primary_location_id' => Location::active()->inRandomOrder()->first()?->id,
                    ]
                );

                // Attach truck types
                $profile = TransporterProfile::where('user_id', $user->id)->first();
                if ($profile && $truckTypes->isNotEmpty()) {
                    $profile->truckTypes()->attach(
                        $truckTypes->random(min(2, $truckTypes->count()))->pluck('id')->toArray()
                    );
                }
            }

            // 8. Create additional demo workspace members
            $allWorkspaces = Workspace::where('owner_id', $demo->id)->get();
            foreach ($allWorkspaces as $workspace) {
                $workspace->addUser($admin, 'admin');

                $memberCount = rand(2, 4);
                $availableUsers = User::whereNotIn('id', [$demo->id, $admin->id])
                    ->whereDoesntHave('workspaces', function ($q) use ($workspace) {
                        $q->where('workspace_id', $workspace->id);
                    })
                    ->get()
                    ->shuffle()
                    ->take(rand(2, 4));

                foreach ($availableUsers as $index => $user) {
                    $workspace->addUser($user, $index === 0 ? 'admin' : 'member');
                }
            }

            $this->command->info('Demo data seeded successfully!');
        });
    }
}
