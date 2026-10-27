<?php

use App\Models\Profile;
use App\Models\TruckType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * One admin list for every public profile.
 *
 * Consignors, carriers, and people who do both are the same record, so there is
 * one place to review them rather than a transporter silo and a consignor
 * silo that could disagree.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function adminUser(): User
{
    return User::factory()->superadmin()->create();
}

it('filters profiles by derived type', function () {
    Profile::factory()->count(2)->consignor()->create();
    Profile::factory()->count(3)->transporter()->create();
    Profile::factory()->both()->create();

    $this->actingAs(adminUser())
        ->get('/admin/profiles?type=transporter')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/profiles/index')
            ->has('profiles.data', 3));
});

it('keeps the type tabs exclusive', function () {
    Profile::factory()->count(2)->consignor()->create();
    Profile::factory()->count(3)->transporter()->create();
    Profile::factory()->both()->create();

    $this->actingAs(adminUser())
        ->get('/admin/profiles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('counts.all', 6)
            ->where('counts.consignor', 2)
            ->where('counts.transporter', 3)
            ->where('counts.both', 1));
});

it('filters by listing status, verification status, and search', function () {
    $pending = Profile::factory()->transporter()->create([
        'status' => 'pending',
        'verification_status' => 'pending',
        'display_name' => 'Pending Carrier',
    ]);

    Profile::factory()->consignor()->create([
        'verification_status' => 'verified',
        'display_name' => 'Verified Shipper',
    ]);

    $user = User::factory()->create(['email' => 'needle@example.test']);
    Profile::factory()->for($user)->consignor()->create(['display_name' => 'Needle Profile']);

    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/admin/profiles?listing_status=pending')
        ->assertInertia(fn ($page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.data.0.slug', $pending->slug));

    $this->actingAs($admin)
        ->get('/admin/profiles?verification_status=pending')
        ->assertInertia(fn ($page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.data.0.slug', $pending->slug));

    $this->actingAs($admin)
        ->get('/admin/profiles?search=needle')
        ->assertInertia(fn ($page) => $page
            ->has('profiles.data', 1)
            ->where('profiles.data.0.display_name', 'Needle Profile'));
});

it('shows one profile with contact, carrier details, documents, and history', function () {
    $profile = Profile::factory()->both()->create([
        'phone' => '+251911000021',
        'status' => 'approved',
        'verification_status' => 'pending',
    ]);
    $truckType = TruckType::factory()->create();
    $profile->truckTypes()->attach($truckType->id);
    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => 'profile-documents/show/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'approved',
    ]);

    $this->actingAs(adminUser())
        ->get("/admin/profiles/{$profile->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/profiles/show')
            ->where('profile.slug', $profile->slug)
            ->where('profile.phone', '+251911000021')
            ->where('profile.type', 'both')
            ->where('profile.truck_types.0.id', $truckType->id)
            ->where('profile.documents.0.id', $document->id)
            ->where('public_url', "/p/{$profile->slug}"));
});

it('verifies a profile and records the moderation', function () {
    $profile = Profile::factory()->create(['verification_status' => 'pending']);
    $admin = adminUser();

    $this->actingAs($admin)
        ->patch("/admin/profiles/{$profile->slug}/verification", [
            'status' => 'verified',
            'note' => 'Documents match',
        ])
        ->assertRedirect();

    $profile->refresh();

    expect($profile->verification_status)->toBe('verified')
        ->and($profile->verification_note)->toBe('Documents match')
        ->and($profile->verification_reviewed_at)->not->toBeNull()
        ->and($profile->moderations()->where('kind', 'verification')->count())->toBe(1);

    $moderation = $profile->moderations()->where('kind', 'verification')->first();

    expect($moderation->from_status)->toBe('pending')
        ->and($moderation->to_status)->toBe('verified')
        ->and($moderation->moderator_id)->toBe($admin->id);
});

it('requires a note when rejecting verification', function () {
    $profile = Profile::factory()->create(['verification_status' => 'pending']);

    $this->actingAs(adminUser())
        ->patch("/admin/profiles/{$profile->slug}/verification", ['status' => 'rejected'])
        ->assertSessionHasErrors('note');
});

it('requires a reason when rejecting a listing and keeps approved edits approved', function () {
    $profile = Profile::factory()->approved()->create(['provides_transport' => true]);
    $admin = adminUser();

    $this->actingAs($admin)
        ->patch("/admin/profiles/{$profile->slug}/listing", ['status' => 'rejected'])
        ->assertSessionHasErrors('reason');

    $truckType = TruckType::factory()->create();

    $this->actingAs($profile->user)->put('/public-profile/transport', [
        'truck_type_ids' => [$truckType->id],
    ])->assertRedirect();

    expect($profile->fresh()->status)->toBe('approved');
});

it('approves a listing and records the previous status', function () {
    $profile = Profile::factory()->pending()->create(['provides_transport' => true]);

    $this->actingAs(adminUser())
        ->patch("/admin/profiles/{$profile->slug}/listing", ['status' => 'approved'])
        ->assertRedirect();

    $profile->refresh();

    expect($profile->status)->toBe('approved')
        ->and($profile->moderations()->where('kind', 'listing')->count())->toBe(1);

    $moderation = $profile->moderations()->where('kind', 'listing')->first();

    expect($moderation->from_status)->toBe('pending')
        ->and($moderation->to_status)->toBe('approved');
});

it('refuses to moderate a listing on a profile that does not carry freight', function () {
    $profile = Profile::factory()->consignor()->create();

    $this->actingAs(adminUser())
        ->patch("/admin/profiles/{$profile->slug}/listing", ['status' => 'approved'])
        ->assertSessionHasErrors('status');

    expect($profile->fresh()->status)->toBeNull();
});

it('reviews an uploaded document', function () {
    $profile = Profile::factory()->transporter()->create();
    $document = $profile->documents()->create([
        'type' => 'insurance',
        'original_name' => 'insurance.pdf',
        'stored_path' => 'profile-documents/review/insurance.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
        'status' => 'pending',
    ]);
    $admin = adminUser();

    $this->actingAs($admin)
        ->patch("/admin/profiles/documents/{$document->id}/review", ['status' => 'approved'])
        ->assertRedirect();

    $document->refresh();

    expect($document->status)->toBe('approved')
        ->and($document->reviewed_by)->toBe($admin->id)
        ->and($document->reviewed_at)->not->toBeNull();
});

it('redirects the old admin transporter URLs', function () {
    $profile = Profile::factory()->approved()->create(['provides_transport' => true]);
    $admin = adminUser();

    $this->actingAs($admin)->get('/admin/transporters')->assertRedirect('/admin/profiles?type=transporter');
    $this->actingAs($admin)->get("/admin/transporters/{$profile->slug}")->assertRedirect("/admin/profiles/{$profile->slug}");
});

it('refuses a non-superadmin everywhere on the admin surface', function () {
    $profile = Profile::factory()->create();
    $document = $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => 'profile-documents/forbidden/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'pending',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/profiles')->assertForbidden();
    $this->actingAs($user)->get("/admin/profiles/{$profile->slug}")->assertForbidden();
    $this->actingAs($user)
        ->patch("/admin/profiles/{$profile->slug}/verification", ['status' => 'verified'])
        ->assertForbidden();
    $this->actingAs($user)
        ->patch("/admin/profiles/documents/{$document->id}/review", ['status' => 'approved'])
        ->assertForbidden();
});
