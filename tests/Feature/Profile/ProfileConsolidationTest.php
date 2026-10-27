<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\ProfileConsolidation;

it('moves transporter identity, status, and children onto the public profile', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'display_name' => 'Existing Name',
        'phone' => null,
        'provides_transport' => false,
    ]);

    $transporter = TransporterProfile::factory()->for($user)->create([
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

    $transporter->routes()->create([
        'origin_location_id' => Location::factory()->create()->id,
        'destination_location_id' => Location::factory()->create()->id,
    ]);
    $transporter->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => 'transporter-documents/'.$transporter->id.'/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1234,
        'status' => 'approved',
    ]);
    $transporter->moderations()->create([
        'from_status' => 'pending',
        'to_status' => 'approved',
        'reason' => 'Looks good',
        'moderator_id' => User::factory()->create()->id,
    ]);

    app(ProfileConsolidation::class)->backfill();

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
    $profile = Profile::factory()->create(['phone_visible' => true]);
    TransporterProfile::factory()->for($profile->user)->create(['phone_visible' => false]);

    app(ProfileConsolidation::class)->backfill();

    expect($profile->fresh()->phone_visible)->toBeFalse();
});

it('gives a profile-less transporter a unique slug and a draft listing', function () {
    // The old transporter slug is unique inside its own table but can still
    // collide with a slug already on public_profiles.
    Profile::factory()->create(['slug' => 'same-slug', 'display_name' => 'Existing']);

    $carrier = User::factory()->create();
    TransporterProfile::factory()->for($carrier)->create([
        'slug' => 'same-slug',
        'display_name' => 'Carrier',
    ]);

    app(ProfileConsolidation::class)->backfill();

    $profile = Profile::where('user_id', $carrier->id)->firstOrFail();

    expect($profile->slug)->not->toBe('same-slug')
        ->and($profile->provides_transport)->toBeTrue()
        ->and($profile->status)->toBe('draft');
});
