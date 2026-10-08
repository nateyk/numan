<?php

namespace Database\Seeders;

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Enums\TransporterStatus;
use App\Enums\VerificationLevel;
use App\Models\CargoType;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\FreightPostRoute;
use App\Models\Location;
use App\Models\TransporterProfile;
use App\Models\TruckType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
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
    /**
     * A location the demo data depends on, or a hard failure.
     *
     * The posts used `$locations->where('name', 'Shire')->first()?->id`. When Shire
     * was not in any location seeder that quietly became null, the post saved with a
     * null destination, the details page rendered no destination name and no map, and
     * the whole thing looked like a broken feature rather than a missing fixture row.
     *
     * A seeder that cannot find what it needs should say so. Silence here is what
     * cost the debugging.
     *
     * @param  Collection<int, Location>  $locations
     */
    private function resolveLocation($locations, string $name): Location
    {
        $location = $locations->firstWhere('name', $name);

        if (! $location) {
            throw new RuntimeException(sprintf(
                'DemoDataSeeder needs a location named "%s" and none exists. '
                .'Run LocationSeeder and EthiopiaMajorTownsSeeder first, or change '
                .'the demo post to a town that exists. Available: %s',
                $name,
                $locations->pluck('name')->implode(', '),
            ));
        }

        return $location;
    }

    /**
     * A stable 26-character ULID for demo post number $index.
     *
     * Deterministic so re-running this seeder updates the same rows instead of
     * appending a second copy of every demo post. Crockford base32, no timestamp
     * — it is a fixture, not a real identifier, and pretending otherwise would
     * make it sort like one.
     */
    private function deterministicUlid(int $index): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

        $encode = function (int $value) use ($alphabet): string {
            $out = '';

            for ($i = 0; $i < 10; $i++) {
                $out = $alphabet[$value % 32].$out;
                $value = intdiv($value, 32);
            }

            return $out;
        };

        // 'DEMO' prefix in base32, then the index, so the value is recognisable
        // in a database dump and cannot collide with a real ULID.
        $prefix = '';

        foreach (str_split('DEMO') as $character) {
            $position = strpos($alphabet, $character);

            // An explicit int cast, not just (int). strpos returns int|false, and
            // PHP 8.4 raises "String offset cast occurred" on the false branch
            // rather than quietly using an empty string.
            $prefix .= $alphabet[(int) $position];
        }

        return $prefix.$encode($index + 1);
    }

    /**
     * Give a demo post a route snapshot, so the map and the distance render.
     *
     * Without this the Freight Details page shows no map toggle and no distance:
     * the coordinates come from the Location, but the drawn line and the
     * kilometre figure come from freight_post_routes. Demo data that cannot
     * demonstrate the feature it exists to demonstrate reads as a broken feature.
     *
     * The distance is a real haversine between the two towns rather than a
     * made-up number, so the figure on screen is consistent with the line drawn
     * on the map. A seeded 900 km between Addis Ababa and Hawassa would be the
     * kind of wrong that gets reported as a routing bug.
     *
     * Labelled straight_line_estimate because that is exactly what it is. The
     * real router is not called here: a seeder reaching out to a routing API is
     * slow, flaky and dependent on the network.
     */
    private function seedRouteSnapshot(FreightPost $post): void
    {
        $origin = $post->originLocation;
        $destination = $post->destinationLocation;

        if (! $origin || ! $destination) {
            return;
        }

        $originLat = (float) $origin->latitude;
        $originLng = (float) $origin->longitude;
        $destinationLat = (float) $destination->latitude;
        $destinationLng = (float) $destination->longitude;

        if ($originLat === 0.0 || $originLng === 0.0 || $destinationLat === 0.0 || $destinationLng === 0.0) {
            return;
        }

        $distanceMeters = (int) round($this->haversineMeters(
            $originLat,
            $originLng,
            $destinationLat,
            $destinationLng,
        ));

        // Roads are not straight lines. 1.25 is a deliberate understatement of the
        // crow-flight figure so the number looks like a road distance rather than
        // the obviously-too-short straight one.
        $roadDistance = (int) round($distanceMeters * 1.25);

        // 45 km/h average including town sections, which is roughly what a loaded
        // truck on Ethiopian roads actually manages.
        $durationSeconds = (int) round(($roadDistance / 1000 / 45) * 3600);

        FreightPostRoute::updateOrCreate(
            ['freight_post_id' => $post->id],
            [
                'distance_meters' => $roadDistance,
                'duration_seconds' => max($durationSeconds, 60),
                // GeoJSON order is [longitude, latitude]. Reversed, the line draws
                // in the Indian Ocean off the coast of Somalia.
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [$originLng, $originLat],
                        [$destinationLng, $destinationLat],
                    ],
                ],
                'routing_provider' => 'straight_line_estimate',
            ],
        );
    }

    /**
     * Great-circle distance in metres.
     */
    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;

        $toRadians = static fn (float $degrees): float => $degrees * M_PI / 180.0;

        $deltaLat = $toRadians($lat2 - $lat1);
        $deltaLon = $toRadians($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($toRadians($lat1)) * cos($toRadians($lat2)) * sin($deltaLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

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
                    // Was 'Shire', which no location seeder provides. `?->id`
                    // turned that into a null foreign key with no warning, so the
                    // post rendered with no destination and no map and read as a
                    // bug in the map. resolveLocation() now throws instead.
                    'destination_location_id' => $this->resolveLocation($locations, 'Addis Ababa')->id,
                    'origin_exact_lat' => 13.49,
                    'origin_exact_lng' => 39.47,
                    'destination_exact_lat' => 9.03,
                    'destination_exact_lng' => 38.74,
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

            foreach ($freightPosts as $index => $postData) {
                // Keyed on a deterministic ULID built from the array index, not on
                // a freshly generated one.
                //
                // firstOrCreate(['ulid' => Str::ulid()]) can never match anything,
                // because the lookup key is itself random. Every run of this seeder
                // therefore inserted the whole set again, and a developer running it
                // twice to see a fresh screen ended up with duplicate demo freight.
                $ulid = $this->deterministicUlid($index);

                $post = FreightPost::firstOrNew(['ulid' => $ulid]);

                if (! $post->exists) {
                    $post->fill($postData);
                    $post->ulid = $ulid;
                    $post->save();
                }

                $this->seedRouteSnapshot($post);
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
                    // syncWithoutDetaching, not attach. attach() has no guard
                    // against a pair that already exists, so running this seeder
                    // twice died on the unique key — and a seeder that cannot be
                    // run twice is a seeder nobody can safely re-run.
                    $profile->truckTypes()->syncWithoutDetaching(
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
