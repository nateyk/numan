<?php

use App\Models\MarketplaceConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passes the migration preflight when there are no accepted connections', function () {
    $this->artisan('freight:check-connections')
        ->expectsOutputToContain('Safe to add the unique index')
        ->assertSuccessful();
});

it('passes the preflight with one accepted connection for each freight', function () {
    MarketplaceConnection::factory()->count(2)->create();

    $this->artisan('freight:check-connections', ['--limit' => 10])
        ->expectsOutputToContain('Safe to add the unique index')
        ->assertSuccessful();

    expect(MarketplaceConnection::count())->toBe(2);
});
