<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\User;

it('creates freight post with ulid and draft status', function () {
    $user = User::factory()->create();
    $post = FreightPost::factory()->for($user)->create(['status' => FreightPostStatus::Draft->value]);
    expect($post->ulid)->not->toBeNull()
        ->and($post->status)->toBe(FreightPostStatus::Draft->value);
});

it('guards invalid status transition', function () {
    expect(FreightPostStatus::Draft->canTransitionTo(FreightPostStatus::Active))->toBeFalse()
        ->and(FreightPostStatus::Draft->canTransitionTo(FreightPostStatus::Submitted))->toBeTrue();
});
