<?php

use App\Models\Location;
use App\Models\TruckType;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * The public-profile journey as a person actually walks it.
 *
 * A new account lands on the page, fills identity and role, saves, adds carrier
 * details, uploads a document, requests verification, previews the page, and a
 * reviewer approves it. Only then does another person see it.
 *
 * The page's own save endpoints are used throughout. Nothing is seeded into a
 * finished state, because the gaps between screens are where this flow breaks.
 */
it('takes a new account from an empty profile to a verified public carrier listing', function () {
    $this->withoutVite();

    Storage::fake('private');

    $user = User::factory()->create([
        'name' => 'Ada Consignor',
        'email_verified_at' => now(),
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();

    $truckType = TruckType::factory()->create(['name' => 'Flatbed']);
    $origin = Location::factory()->create(['name' => 'Addis Ababa']);
    $destination = Location::factory()->create(['name' => 'Djibouti']);

    // 1. The first visit creates the profile from the account name.
    $this->actingAs($user)
        ->get('/public-profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public-profile/index')
            ->where('profile.display_name', 'Ada Consignor')
            ->where('profile.provides_transport', false)
            ->where('completionScore', 10)
            ->has('locations')
            ->has('truckTypes')
            ->has('accountTypes')
            ->has('legalForms')
            ->has('profile.documents', 0)
            ->has('profile.routes', 0));

    $profile = $user->profile()->firstOrFail();

    expect($profile->slug)->not->toBeEmpty();

    // 2. Identity and role save together, exactly as the page's form does.
    $this->actingAs($user)
        ->patch('/public-profile', [
            'display_name' => 'Ada Haulage',
            'account_type' => 'business',
            'phone' => '+251911000001',
            'phone_numbers' => ['+251911000001', '+25377123456'],
            'whatsapp_numbers' => ['+251911000002', '+25377123457'],
            'whatsapp' => '+251911000002',
            'email' => 'dispatch@ada.test',
            'business_description' => 'Flatbed haulage between Addis Ababa and Djibouti.',
            'primary_location_id' => $origin->id,
            'legal_name' => 'Ada Haulage PLC',
            'legal_form' => 'Private Limited Company (PLC)',
            'legal_form_other' => '',
            'phone_visible' => true,
            'whatsapp_visible' => false,
            'email_visible' => true,
            'posts_freight' => true,
            'provides_transport' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $profile = $profile->fresh();

    expect($profile->display_name)->toBe('Ada Haulage')
        ->and($profile->phone)->toBe('+251911000001')
        ->and($profile->phone_numbers)->toBe(['+251911000001', '+25377123456'])
        ->and($profile->posts_freight)->toBeTrue()
        ->and($profile->provides_transport)->toBeTrue()
        ->and($profile->status)->toBe('draft')
        ->and($profile->completion_score)->toBe(40);

    // 3. The management page now exposes the carrier section and saved values.
    $this->actingAs($user)
        ->get('/public-profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public-profile/index')
            ->where('profile.display_name', 'Ada Haulage')
            ->where('profile.status', 'draft')
            ->where('profile.provides_transport', true)
            ->where('completionScore', 40)
            ->has('profile.documents', 0)
            ->has('profile.routes', 0));

    // 4. Carrier details submit the listing for review.
    $this->actingAs($user)
        ->put('/public-profile/transport', [
            'truck_type_ids' => [$truckType->id],
            'routes' => [[
                'origin_location_id' => $origin->id,
                'destination_location_id' => $destination->id,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $profile = $profile->fresh();

    expect($profile->status)->toBe('pending')
        ->and($profile->truckTypes()->count())->toBe(1)
        ->and($profile->routes()->count())->toBe(1)
        ->and($profile->completion_score)->toBe(90);

    // 5. A private document upload completes the score.
    $this->actingAs($user)
        ->post('/public-profile/documents', [
            'type' => 'business_registration',
            'file' => UploadedFile::fake()->create('registration.pdf', 250, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $profile = $profile->fresh();

    expect($profile->documents()->count())->toBe(1)
        ->and($profile->completion_score)->toBe(100);

    // 6. Verification is requested only after the identity is saved.
    $this->actingAs($user)
        ->post('/public-profile/verification')
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $profile = $profile->fresh();

    expect($profile->verification_status)->toBe('pending')
        ->and($profile->verification_requested_at)->not->toBeNull();

    // 7. The owner preview shows exactly the channels the privacy switches allow.
    $this->actingAs($user)
        ->get("/p/{$profile->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profiles/show')
            ->where('is_owner', true)
            ->where('profile.display_name', 'Ada Haulage')
            ->where('profile.verification_status', 'pending')
            ->where('contact_preview.phone', '+251911000001')
            ->where('contact_preview.email', 'dispatch@ada.test')
            ->where('contact_preview.whatsapp', null));

    // 8. A pending listing is still private to everybody else.
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get("/p/{$profile->slug}")
        ->assertNotFound();

    // 9. A reviewer approves the listing and the identity.
    $admin = User::factory()->superadmin()->create();

    $this->actingAs($admin)
        ->patch("/admin/profiles/{$profile->slug}/listing", [
            'status' => 'approved',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->patch("/admin/profiles/{$profile->slug}/verification", [
            'status' => 'verified',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // 10. Another person can now open the page, and the contact details stay out
    // of the payload entirely.
    $response = $this->actingAs($viewer->fresh())
        ->get("/p/{$profile->slug}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profiles/show')
            ->where('is_owner', false)
            ->where('profile.is_verified', true)
            ->where('noindex', false)
            ->missing('contact_preview'));

    expect($response->getContent())
        ->not->toContain('+251911000001')
        ->not->toContain('dispatch@ada.test');
});
