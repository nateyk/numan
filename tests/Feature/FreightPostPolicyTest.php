<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\User;
use App\Policies\FreightPostPolicy;

it('prevents non-owner from updating draft', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Draft->value]);
    expect((new FreightPostPolicy)->update($other, $post))->toBeFalse();
});

it('allows owner to update draft', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Draft->value]);
    expect((new FreightPostPolicy)->update($owner, $post))->toBeTrue();
});

it('prevents owner from updating active post', function () {
    $owner = User::factory()->create();
    $post = FreightPost::factory()->for($owner)->create(['status' => FreightPostStatus::Active->value]);
    expect((new FreightPostPolicy)->update($owner, $post))->toBeFalse();
});

it('allows superadmin to moderate', function () {
    $admin = User::factory()->create(['is_superadmin' => true]);
    expect((new FreightPostPolicy)->moderate($admin))->toBeTrue();
});

it('prevents regular user from moderating', function () {
    $user = User::factory()->create();
    expect((new FreightPostPolicy)->moderate($user))->toBeFalse();
});
