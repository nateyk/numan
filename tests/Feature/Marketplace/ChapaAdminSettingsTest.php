<?php

use App\Models\ChapaPaymentSetting;
use App\Models\ChapaWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_superadmin' => true,
        'email_verified_at' => now(),
    ]);
});

it('shows webhook delivery counters without exposing credentials or signed payloads', function () {
    ChapaPaymentSetting::query()->create([
        'id' => 1,
        'mode' => 'test',
        'public_key' => 'CHAPUBK_TEST_sensitive',
        'secret_key' => 'CHASECK_TEST_sensitive',
        'webhook_secret' => 'super-sensitive-webhook-secret',
        'updated_by' => $this->admin->id,
    ]);

    ChapaWebhookEvent::query()->create([
        'event' => 'charge.success',
        'tx_ref' => 'private-ref-1',
        'payload' => ['email' => 'private@example.test', 'status' => 'success'],
        'status' => 'processed',
        'processed_at' => now(),
    ]);
    ChapaWebhookEvent::query()->create([
        'event' => 'charge.failed',
        'tx_ref' => 'private-ref-2',
        'payload' => ['email' => 'another@example.test'],
        'status' => 'failed',
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/chapa-settings');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/chapa-settings')
        ->where('webhook_status.pending_count', 0)
        ->where('webhook_status.failed_count', 1)
        ->where('settings.public_key_source', 'dashboard')
        ->where('settings.secret_key_source', 'dashboard')
        ->where('settings.webhook_secret_source', 'dashboard')
        ->has('webhook_status.last_received_at')
        ->has('webhook_status.last_processed_at')
        ->etc());

    $body = $response->getContent();
    foreach (['CHAPUBK_TEST_sensitive', 'CHASECK_TEST_sensitive', 'super-sensitive-webhook-secret', 'private@example.test', 'private-ref-1'] as $privateValue) {
        expect($body)->not->toContain($privateValue);
    }
});

it('rejects a mismatched mode without replacing saved Chapa credentials', function () {
    ChapaPaymentSetting::query()->create([
        'id' => 1,
        'mode' => 'test',
        'public_key' => 'CHAPUBK_TEST_existing',
        'secret_key' => 'CHASECK_TEST_existing',
        'webhook_secret' => 'existing-webhook-secret',
        'updated_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->put('/admin/chapa-settings', [
        'mode' => 'live',
        'public_key' => 'CHAPUBK_LIVE_new',
        'secret_key' => 'CHASECK_TEST_wrong',
        'webhook_secret' => 'new-live-webhook-secret',
    ])->assertSessionHasErrors('secret_key');

    $settings = ChapaPaymentSetting::query()->findOrFail(1);
    expect($settings->mode)->toBe('test')
        ->and($settings->secret_key)->toBe('CHASECK_TEST_existing');
});
