<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTransporterUserForInterest(bool $approved = true): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $location->id,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    if ($approved) {
        TransporterProfile::factory()->create(['user_id' => $user->id, 'status' => 'approved']);
    }

    return $user->fresh();
}

it('prevents duplicate active interest', function () {
    $post = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);
    $transporter = createTransporterUserForInterest(approved: true);
    FreightInterest::factory()->create(['freight_post_id' => $post->id, 'user_id' => $transporter->id, 'status' => FreightInterestStatus::Submitted->value]);
    expect(FreightInterest::where('freight_post_id', $post->id)->where('user_id', $transporter->id)->where('status', FreightInterestStatus::Submitted->value)->exists())->toBeTrue();
});

it('guards interest status transitions', function () {
    expect(FreightInterestStatus::Submitted->canTransitionTo(FreightInterestStatus::Shortlisted))->toBeTrue()
        ->and(FreightInterestStatus::Submitted->canTransitionTo(FreightInterestStatus::Selected))->toBeFalse();
});

it('scopes active interests', function () {
    $post = FreightPost::factory()->create();
    FreightInterest::factory()->create(['freight_post_id' => $post->id, 'status' => FreightInterestStatus::Submitted->value]);
    FreightInterest::factory()->create(['freight_post_id' => $post->id, 'status' => FreightInterestStatus::Rejected->value]);
    expect(FreightInterest::active()->count())->toBe(1);
});
