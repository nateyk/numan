<?php

use App\Models\Profile;
use App\Models\ProfileDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * The old profile surfaces are gone.
 *
 * Every URL that used to host a second, transporter-shaped profile now points
 * at the single `/public-profile` management page. The links keep working so
 * bookmarks, emails, and cached pages do not land on a 404, but they no longer
 * offer a second way to edit the same identity.
 */
uses(RefreshDatabase::class);

it('redirects the old profile surfaces to the public profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/my-transporter-profile')->assertRedirect('/public-profile');
    $this->actingAs($user)->post('/my-transporter-profile')->assertRedirect('/public-profile');
    $this->actingAs($user)->put('/my-transporter-profile')->assertRedirect('/public-profile');
    $this->actingAs($user)->patch('/my-transporter-profile')->assertRedirect('/public-profile');
    $this->actingAs($user)->get('/settings/marketplace-details')->assertRedirect('/public-profile');
    $this->actingAs($user)->patch('/settings/marketplace-details')->assertRedirect('/public-profile');
});

it('removes the old capability and profile mutation endpoints', function () {
    expect(Route::has('settings.capabilities.update'))->toBeFalse()
        ->and(Route::has('transporter.profile.store'))->toBeFalse()
        ->and(Route::has('transporter.profile.update'))->toBeFalse();
});

it('redirects a legacy document download to the consolidated document route', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->transporter()->create();
    $document = ProfileDocument::create([
        'profile_id' => $profile->id,
        'type' => 'license',
        'original_name' => 'licence.pdf',
        'stored_path' => 'profile-documents/legacy/licence.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get("/my-transporter-profile/documents/{$document->id}/download")
        ->assertRedirect("/public-profile/documents/{$document->id}/download");
});

it('sends an unknown legacy document download back to the profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/my-transporter-profile/documents/999999/download')
        ->assertRedirect('/public-profile');
});
