<?php

use App\Http\Resources\PostOwnerSummary;
use App\Models\FreightPost;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The single verification badge follows the person across every surface.
 *
 * A carrier directory that says "verified" while the freight owner card says
 * nothing is the kind of inconsistency that makes a badge meaningless.
 */
uses(RefreshDatabase::class);

it('shows the verified badge on public profiles and owner summaries', function () {
    $profile = Profile::factory()->verified()->create([
        'provides_transport' => true,
        'status' => 'approved',
    ]);
    FreightPost::factory()->for($profile->user)->create(['status' => 'active']);

    expect(PostOwnerSummary::flat($profile->user)['is_verified'])->toBeTrue();

    $this->get("/p/{$profile->slug}")
        ->assertInertia(fn ($page) => $page->where('profile.is_verified', true));

    $this->get('/transporters')->assertRedirect('/freight');
});

it('shows a rejection note and allows resubmission', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'account_type' => 'personal',
        'display_name' => 'Carrier',
        'phone' => '+251911000001',
        'verification_status' => 'rejected',
        'verification_note' => 'Upload the business registration.',
    ]);

    $this->actingAs($user)->get('/public-profile')
        ->assertInertia(fn ($page) => $page
            ->where('profile.verification_status', 'rejected')
            ->where('profile.verification_note', 'Upload the business registration.'));

    $this->actingAs($user)->post('/public-profile/verification')->assertRedirect();
    expect($profile->fresh()->verification_status)->toBe('pending');
});

it('shows the owner the latest listing rejection reason', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->rejected()->create([
        'user_id' => $user->id,
        'provides_transport' => true,
    ]);
    $profile->moderations()->create([
        'kind' => 'listing',
        'from_status' => 'pending',
        'to_status' => 'rejected',
        'reason' => 'Insurance certificate expired.',
        'moderator_id' => User::factory()->superadmin()->create()->id,
    ]);

    $this->actingAs($user)->get('/public-profile')
        ->assertInertia(fn ($page) => $page
            ->where('listing_rejection_reason', 'Insurance certificate expired.'));
});
