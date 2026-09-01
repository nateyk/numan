<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expires active freight past pickup window', function () {
    $expiredPost = FreightPost::factory()->create([
        'status' => FreightPostStatus::Active->value,
        'pickup_date' => now()->subDays(5)->toDateString(),
        'pickup_date_flexibility_days' => 0,
    ]);

    $activePost = FreightPost::factory()->create([
        'status' => FreightPostStatus::Active->value,
        'pickup_date' => now()->addDays(5)->toDateString(),
        'pickup_date_flexibility_days' => 0,
    ]);

    $this->artisan('freight:expire')->assertExitCode(0);

    expect($expiredPost->fresh()->status)->toBe(FreightPostStatus::Expired->value)
        ->and($activePost->fresh()->status)->toBe(FreightPostStatus::Active->value);
});

it('does not expire draft posts', function () {
    $draft = FreightPost::factory()->create([
        'status' => FreightPostStatus::Draft->value,
        'pickup_date' => now()->subDays(5)->toDateString(),
    ]);

    $this->artisan('freight:expire')->assertExitCode(0);

    expect($draft->fresh()->status)->toBe(FreightPostStatus::Draft->value);
});
