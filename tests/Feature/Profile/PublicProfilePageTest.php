<?php

use App\Models\ContactReveal;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Inertia asserts the page file exists; the built manifest is irrelevant to
    // these server-rendered prop assertions and goes stale between builds.
    $this->withoutVite();
});

it('shows a consignor profile to the owner but 404s for a stranger', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    $this->actingAs($owner)->get("/p/{$profile->slug}")->assertOk();

    $this->actingAs(User::factory()->create())
        ->get("/p/{$profile->slug}")
        ->assertNotFound();
});

it('shows a consignor profile to a viewer already in the reveal ledger', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create(['posts_freight' => true]);
    ContactReveal::create([
        'revealer_id' => $viewer->id,
        'revealed_id' => $owner->id,
    ]);

    $this->actingAs($viewer)->get("/p/{$profile->slug}")->assertOk();
});

it('shows an approved carrier publicly and hides pending carriers', function () {
    $approved = Profile::factory()->approved()->create(['provides_transport' => true]);
    $pending = Profile::factory()->pending()->create(['provides_transport' => true]);

    $this->get("/p/{$approved->slug}")->assertOk();
    $this->get("/p/{$pending->slug}")->assertNotFound();
});

it('never serializes contact, legal name, or document names publicly', function () {
    $profile = Profile::factory()->approved()->create([
        'provides_transport' => true,
        'phone' => '+251911000001',
        'legal_name' => 'Private PLC',
    ]);
    $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => 'profile-documents/1/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
        'status' => 'approved',
    ]);

    $this->get("/p/{$profile->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profiles/show')
            ->missing('profile.phone')
            ->missing('profile.legal_name')
            ->missing('profile.documents')
            ->where('profile.has_documents', true));
});

it('gives the owner a contact preview and a revealed viewer a note without contact', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = Profile::factory()->approved()->create([
        'user_id' => $owner->id,
        'provides_transport' => true,
        'phone' => '+251911000001',
        'phone_visible' => true,
    ]);
    ContactReveal::create([
        'revealer_id' => $viewer->id,
        'revealed_id' => $owner->id,
    ]);

    $this->actingAs($owner)->get("/p/{$profile->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('is_owner', true)
            ->where('contact_preview.phone', '+251911000001'));

    $this->actingAs($viewer)->get("/p/{$profile->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('is_owner', false)
            ->where('already_revealed', true)
            ->missing('contact_preview'));
});

it('never exposes document names or contact fields on the public page', function () {
    $profile = Profile::factory()->approved()->create([
        'provides_transport' => true,
        'phone' => '+251911000001',
        'email' => 'carrier@example.com',
        'legal_name' => 'Carrier PLC',
    ]);
    $profile->documents()->create([
        'type' => 'license',
        'original_name' => 'secret-licence.pdf',
        'stored_path' => 'profile-documents/'.$profile->id.'/secret-licence.pdf',
        'mime' => 'application/pdf',
        'size' => 123,
        'status' => 'approved',
    ]);

    $this->get("/p/{$profile->slug}")
        ->assertOk()
        ->assertDontSee('secret-licence.pdf')
        ->assertDontSee('+251911000001')
        ->assertDontSee('carrier@example.com')
        ->assertDontSee('Carrier PLC')
        ->assertInertia(fn ($page) => $page
            ->where('profile.has_documents', true)
            ->where('profile.documents_reviewed', true));
});

it('redirects the retired carrier directory and legacy profile URLs', function () {
    $approved = Profile::factory()->approved()->create(['provides_transport' => true]);
    Profile::factory()->pending()->create(['provides_transport' => true]);

    $this->get('/transporters')->assertRedirect('/freight');

    $this->get("/transporters/{$approved->slug}")
        ->assertStatus(301)
        ->assertRedirect("/p/{$approved->slug}");
});

it('does not confirm a hidden consignor through the old profile URL', function () {
    // The old controller only served approved carriers. Redirecting any known
    // slug would let a stranger use the 301 as an existence check for a
    // consignor whose /p page is deliberately a 404.
    $consignor = Profile::factory()->consignor()->create();

    $this->get("/transporters/{$consignor->slug}")->assertNotFound();
});
