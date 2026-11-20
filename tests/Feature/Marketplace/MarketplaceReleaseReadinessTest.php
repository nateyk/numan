<?php

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function configuredReadinessPreflight(): void
{
    config()->set('chapa.mode', 'test');
    config()->set('chapa.baseUrl', 'https://api.chapa.co/v1');
    config()->set('chapa.secretKey', 'CHASECK_TEST-synthetic-not-real');
    config()->set('chapa.webhookSecret', 'synthetic-test-webhook-secret');
    config()->set('app.url', 'https://numan.example.test');
    config()->set('mail.default', 'smtp');
    config()->set('queue.default', 'database');
}

it('fails the strict read-only release check when credentials and callback origin are missing', function () {
    config()->set('chapa.secretKey', null);
    config()->set('chapa.webhookSecret', null);
    config()->set('app.url', 'http://localhost');
    config()->set('mail.default', 'array');
    config()->set('queue.default', 'sync');

    Http::fake();

    $this->artisan('marketplace:release-check', ['--strict' => true])
        ->expectsOutputToContain('Chapa private key: MISSING')
        ->expectsOutputToContain('HTTPS NOT configured')
        ->expectsOutputToContain('NOT VERIFIED HERE')
        ->assertFailed();

    Http::assertNothingSent();
});

it('can pass the configuration-only release check without triggering financial operations', function () {
    configuredReadinessPreflight();

    Http::fake();

    $this->artisan('marketplace:release-check', ['--strict' => true])
        ->expectsOutputToContain('configured (value hidden)')
        ->expectsOutputToContain('Marketplace tables: present')
        ->expectsOutputToContain('NOT VERIFIED HERE')
        ->expectsOutputToContain('Configuration preflight has no detected blockers.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('flags older unresolved Chapa attempts without settling or modifying them', function () {
    configuredReadinessPreflight();
    $this->seed(MarketplacePlanSeeder::class);

    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-readiness-old-pending',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'initialized',
        'provider' => 'chapa',
        'mode' => 'test',
    ]);
    DB::table('marketplace_payment_transactions')->where('id', $tx->id)
        ->update(['created_at' => now()->subDays(4)]);

    Http::fake();

    $this->artisan('marketplace:release-check', ['--strict' => true])
        ->expectsOutputToContain('Outstanding Chapa attempts older than 48 hours: 1')
        ->assertFailed();

    Http::assertNothingSent();

    expect($tx->fresh()->status)->toBe('initialized')
        ->and($tx->fresh()->verified_at)->toBeNull()
        ->and($tx->fresh()->receipt)->toBeNull();
});
