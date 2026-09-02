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
use App\Policies\FreightInterestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeConsignor(): User
{
    $user = User::factory()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $loc->id,
    ]);
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);

    return $user->fresh();
}

function makeTransporter(bool $approved = true, bool $provides = true): User
{
    $user = User::factory()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => false,
        'provides_transport' => $provides,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $loc->id,
    ]);
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);
    if ($approved) {
        TransporterProfile::factory()->create(['user_id' => $user->id, 'status' => 'approved']);
    }

    return $user->fresh();
}

it('prevents consignor from creating interest on own post', function () {
    $consignor = makeConsignor();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    $policy = new FreightInterestPolicy;
    expect($policy->create($consignor, $post))->toBeFalse();
});

it('requires approved transporter and active post for create', function () {
    $consignor = makeConsignor();
    $transporter = makeTransporter(approved: true);
    $unapproved = makeTransporter(approved: false);
    $noTransport = makeTransporter(approved: false, provides: false);
    $postActive = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    $postDraft = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Draft->value]);
    $policy = new FreightInterestPolicy;
    expect($policy->create($transporter, $postActive))->toBeTrue()
        ->and($policy->create($unapproved, $postActive))->toBeFalse()
        ->and($policy->create($noTransport, $postActive))->toBeFalse()
        ->and($policy->create($transporter, $postDraft))->toBeFalse();
});

it('prevents duplicate active interest', function () {
    $consignor = makeConsignor();
    $transporter = makeTransporter();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Submitted->value]);
    $policy = new FreightInterestPolicy;
    expect($policy->create($transporter, $post))->toBeFalse();
});

it('allows withdraw only for owner on submitted or shortlisted', function () {
    $consignor = makeConsignor();
    $transporter = makeTransporter();
    $other = makeTransporter();
    $post = FreightPost::factory()->for($consignor)->create();
    $submitted = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Submitted->value]);
    $shortlisted = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Shortlisted->value]);
    $rejected = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Rejected->value]);
    $policy = new FreightInterestPolicy;
    expect($policy->withdraw($transporter, $submitted))->toBeTrue()
        ->and($policy->withdraw($transporter, $shortlisted))->toBeTrue()
        ->and($policy->withdraw($transporter, $rejected))->toBeFalse()
        ->and($policy->withdraw($other, $submitted))->toBeFalse();
});

it('allows updateStatus only for post owner', function () {
    $consignor = makeConsignor();
    $transporter = makeTransporter();
    $other = makeTransporter();
    $post = FreightPost::factory()->for($consignor)->create();
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create();
    $policy = new FreightInterestPolicy;
    expect($policy->updateStatus($consignor, $interest))->toBeTrue()
        ->and($policy->updateStatus($other, $interest))->toBeFalse()
        ->and($policy->updateStatus($transporter, $interest))->toBeFalse();
});
