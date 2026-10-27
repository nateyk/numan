<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TruckType;
use App\Models\User;
use App\Services\ProfileConsolidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The migration path between the legacy transporter table and the one profile.
 *
 * The real schema no longer has the old tables, so the backfill tests recreate
 * them with the exact original definition. The rollback test goes the other way:
 * restoreLegacy() recreates the old tables itself and copies consolidated rows
 * back, so an incident rollback is a migration rather than a manual apology.
 */

/**
 * Insert a legacy transporter row and return its id.
 *
 * @param  array<string, mixed>  $attributes
 */
function insertLegacyTransporter(array $attributes = []): int
{
    return DB::table('transporter_profiles')->insertGetId(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => 'carrier-'.Str::lower(Str::random(8)),
        'display_name' => 'Carrier Co',
        'legal_name' => null,
        'phone' => null,
        'whatsapp' => null,
        'email' => null,
        'business_description' => null,
        'is_company' => false,
        'status' => 'draft',
        'verification_level' => 'unverified',
        'completion_score' => 0,
        'phone_visible' => false,
        'whatsapp_visible' => false,
        'email_visible' => false,
        'primary_location_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

it('moves transporter identity, status, and children onto the public profile', function () {
    $consolidation = app(ProfileConsolidation::class);
    $consolidation->createLegacySchema();

    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'display_name' => 'Existing Name',
        'phone' => null,
        'provides_transport' => false,
    ]);

    $transporterId = insertLegacyTransporter([
        'user_id' => $user->id,
        'slug' => 'carrier-co',
        'display_name' => 'Carrier Co',
        'phone' => '+251911000001',
        'email' => 'carrier@example.com',
        'status' => 'approved',
        'verification_level' => 'verified',
        'completion_score' => 80,
        'phone_visible' => false,
        'whatsapp_visible' => true,
        'email_visible' => false,
    ]);

    DB::table('transporter_routes')->insert([
        'transporter_profile_id' => $transporterId,
        'origin_location_id' => Location::factory()->create()->id,
        'destination_location_id' => Location::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('transporter_documents')->insert([
        'transporter_profile_id' => $transporterId,
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => 'transporter-documents/'.$transporterId.'/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1234,
        'status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('transporter_profile_moderation')->insert([
        'transporter_profile_id' => $transporterId,
        'from_status' => 'pending',
        'to_status' => 'approved',
        'reason' => 'Looks good',
        'moderator_id' => User::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $consolidation->backfill();

    $profile->refresh();

    expect($profile->display_name)->toBe('Existing Name')
        ->and($profile->phone)->toBe('+251911000001')
        ->and($profile->provides_transport)->toBeTrue()
        ->and($profile->slug)->toBe('carrier-co')
        ->and($profile->status)->toBe('approved')
        ->and($profile->verification_status)->toBe('verified')
        ->and($profile->completion_score)->toBe(80)
        ->and($profile->phone_visible)->toBeFalse();

    expect($profile->routes()->count())->toBe(1)
        ->and($profile->documents()->count())->toBe(1)
        ->and($profile->moderations()->where('kind', 'listing')->count())->toBe(1);
});

it('never widens contact visibility during the backfill', function () {
    $consolidation = app(ProfileConsolidation::class);
    $consolidation->createLegacySchema();

    $profile = Profile::factory()->create(['phone_visible' => true]);
    insertLegacyTransporter([
        'user_id' => $profile->user_id,
        'phone_visible' => false,
    ]);

    $consolidation->backfill();

    expect($profile->fresh()->phone_visible)->toBeFalse();
});

it('gives a profile-less transporter a unique slug and a draft listing', function () {
    // The old transporter slug is unique inside its own table but can still
    // collide with a slug already on public_profiles.
    $consolidation = app(ProfileConsolidation::class);
    $consolidation->createLegacySchema();

    Profile::factory()->create(['slug' => 'same-slug', 'display_name' => 'Existing']);

    $carrier = User::factory()->create();
    insertLegacyTransporter([
        'user_id' => $carrier->id,
        'slug' => 'same-slug',
        'display_name' => 'Carrier',
    ]);

    $consolidation->backfill();

    $profile = Profile::where('user_id', $carrier->id)->firstOrFail();

    expect($profile->slug)->not->toBe('same-slug')
        ->and($profile->provides_transport)->toBeTrue()
        ->and($profile->status)->toBe('draft');
});

it('restores the legacy tables and rows from a consolidated profile', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->transporter()->verified()->create([
        'display_name' => 'Restored Carrier',
        'legal_name' => 'Restored Carrier PLC',
        'phone' => '+251911000002',
        'whatsapp' => '+251911000003',
        'email' => 'restored@example.test',
        'business_description' => 'Long-haul carrier',
        'account_type' => 'business',
        'status' => 'approved',
        'completion_score' => 73,
        'phone_visible' => true,
        'whatsapp_visible' => false,
        'email_visible' => true,
        'primary_location_id' => Location::factory()->create()->id,
    ]);

    $truck = TruckType::factory()->create();
    $profile->truckTypes()->attach($truck);

    $origin = Location::factory()->create();
    $destination = Location::factory()->create();

    $profile->routes()->create([
        'origin_location_id' => $origin->id,
        'destination_location_id' => $destination->id,
    ]);

    $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => 'profile-documents/'.$profile->id.'/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1234,
        'status' => 'approved',
    ]);

    $moderator = User::factory()->create();

    $profile->moderations()->create([
        'kind' => 'listing',
        'from_status' => 'pending',
        'to_status' => 'approved',
        'reason' => 'Looks good',
        'moderator_id' => $moderator->id,
    ]);

    // Verification moderations have no legacy equivalent and are not restored.
    $profile->moderations()->create([
        'kind' => 'verification',
        'from_status' => 'unverified',
        'to_status' => 'verified',
        'reason' => 'Documents checked',
        'moderator_id' => $moderator->id,
    ]);

    app(ProfileConsolidation::class)->restoreLegacy();

    $row = DB::table('transporter_profiles')->where('user_id', $user->id)->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->id)->toBe($profile->id)
        ->and($row->slug)->toBe(Str::slug($profile->slug))
        ->and($row->display_name)->toBe('Restored Carrier')
        ->and($row->legal_name)->toBe('Restored Carrier PLC')
        ->and($row->phone)->toBe('+251911000002')
        ->and($row->whatsapp)->toBe('+251911000003')
        ->and($row->email)->toBe('restored@example.test')
        ->and($row->business_description)->toBe('Long-haul carrier')
        ->and((bool) $row->is_company)->toBeTrue()
        ->and($row->status)->toBe('approved')
        ->and($row->verification_level)->toBe('verified')
        ->and((int) $row->completion_score)->toBe(73)
        ->and((bool) $row->phone_visible)->toBeTrue()
        ->and((bool) $row->whatsapp_visible)->toBeFalse()
        ->and((bool) $row->email_visible)->toBeTrue()
        ->and((int) $row->primary_location_id)->toBe($profile->primary_location_id);

    expect(DB::table('transporter_profile_truck_type')->where('transporter_profile_id', $row->id)->count())->toBe(1)
        ->and(DB::table('transporter_routes')->where('transporter_profile_id', $row->id)->count())->toBe(1)
        ->and(DB::table('transporter_documents')->where('transporter_profile_id', $row->id)->count())->toBe(1)
        ->and(DB::table('transporter_profile_moderation')->where('transporter_profile_id', $row->id)->count())->toBe(1);
});
