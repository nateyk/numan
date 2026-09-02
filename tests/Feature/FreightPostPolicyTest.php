<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\FreightPostPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUser(): User
{
    $user = User::factory()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $loc->id,
    ]);
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);

    return $user->fresh();
}

it('allows owner to view their freight post', function () {
    $user = makeUser();
    $post = FreightPost::factory()->for($user)->create();
    expect((new FreightPostPolicy)->view($user, $post))->toBeTrue();
});

it('prevents non-owner from viewing draft but allows active', function () {
    $owner = makeUser();
    $other = makeUser();
    $draft = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Draft->value]);
    $active = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Active->value]);
    $policy = new FreightPostPolicy;
    expect($policy->view($other, $draft))->toBeFalse()
        ->and($policy->view($other, $active))->toBeTrue();
});

it('allows create only for onboarded consignor with posts_freight', function () {
    $consignor = makeUser();
    $policy = new FreightPostPolicy;
    expect($policy->create($consignor))->toBeTrue();

    $noFreight = makeUser();
    $noFreight->profile()->update(['posts_freight' => false]);
    expect($policy->create($noFreight->fresh()))->toBeFalse();

    $notOnboarded = User::factory()->create();
    $ws = Workspace::factory()->create(['owner_id' => $notOnboarded->id]);
    $ws->users()->attach($notOnboarded->id, ['role' => 'owner']);
    $notOnboarded->update(['current_workspace_id' => $ws->id]);
    expect($policy->create($notOnboarded->fresh()))->toBeFalse();
});

it('allows update and submit only for owner on draft', function () {
    $owner = makeUser();
    $other = makeUser();
    $draft = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Draft->value]);
    $active = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Active->value]);
    $policy = new FreightPostPolicy;
    expect($policy->update($owner, $draft))->toBeTrue()
        ->and($policy->submit($owner, $draft))->toBeTrue()
        ->and($policy->update($other, $draft))->toBeFalse()
        ->and($policy->update($owner, $active))->toBeFalse();
});

it('allows moderate only for superadmin', function () {
    $admin = User::factory()->superadmin()->create();
    $ws = Workspace::factory()->create(['owner_id' => $admin->id]);
    $ws->users()->attach($admin->id, ['role' => 'owner']);
    $admin->update(['current_workspace_id' => $ws->id]);
    $normal = makeUser();
    $policy = new FreightPostPolicy;
    expect($policy->moderate($admin->fresh()))->toBeTrue()
        ->and($policy->moderate($normal))->toBeFalse();
});
