<?php

use App\Models\ChapaPaymentSetting;
use App\Models\MarketplacePaymentTransaction;
use App\Models\User;
use App\Services\ChapaConfiguration;
use App\Services\ChapaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function chapaAdmin(): User
{
    return User::factory()->create(['is_superadmin' => true]);
}

function chapaTestKeys(): array
{
    return [
        'mode' => 'test',
        'public_key' => 'CHAPUBK_TEST-aBcPublicKeyExample123',
        'secret_key' => 'CHASECK_TEST-aBcSecretKeyExample123',
        'webhook_secret' => 'numan-test-webhook-secret-123456789',
    ];
}

it('requires superadmin authentication for the settings page and updates', function () {
    $user = User::factory()->create();
    $data = chapaTestKeys();

    $this->get('/admin/chapa-settings')->assertRedirect('/login');
    $this->put('/admin/chapa-settings', $data)->assertRedirect('/login');

    $this->actingAs($user)->get('/admin/chapa-settings')->assertForbidden();
    $this->actingAs($user)->put('/admin/chapa-settings', $data)->assertForbidden();

    expect(ChapaPaymentSetting::count())->toBe(0);
});

it('shows only masked credential status and an HTTPS webhook URL to a superadmin', function () {
    config()->set('chapa.secretKey', 'CHASECK_TEST-env-not-for-inertia');
    config()->set('chapa.publicKey', 'CHAPUBK_TEST-env-not-for-inertia');
    config()->set('chapa.webhookSecret', 'environment-webhook-secret');

    $this->actingAs(chapaAdmin())->get('/admin/chapa-settings')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/chapa-settings')
            ->where('settings.mode', 'test')
            ->where('settings.public_key_source', 'environment')
            ->where('settings.secret_key_source', 'environment')
            ->where('settings.webhook_secret_source', 'environment')
            ->where('webhook_url', route('webhooks.chapa')))
        ->assertDontSee('CHASECK_TEST-env-not-for-inertia')
        ->assertDontSee('environment-webhook-secret');
});

it('encrypts keys at rest and uses them for the actual Chapa v1 API requests', function () {
    $admin = chapaAdmin();
    $keys = chapaTestKeys();

    $this->actingAs($admin)->put('/admin/chapa-settings', $keys)
        ->assertRedirect()
        ->assertSessionHas('success');

    $stored = ChapaPaymentSetting::findOrFail(1);
    expect($stored->secret_key)->toBe($keys['secret_key'])
        ->and($stored->webhook_secret)->toBe($keys['webhook_secret'])
        ->and($stored->public_key)->toBe($keys['public_key'])
        ->and($stored->getRawOriginal('secret_key'))->not->toContain($keys['secret_key'])
        ->and($stored->getRawOriginal('webhook_secret'))->not->toContain($keys['webhook_secret'])
        ->and($stored->toArray())->not->toHaveKey('secret_key')
        ->and($stored->updated_by)->toBe($admin->id);

    $this->actingAs($admin)->get('/admin/chapa-settings')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.secret_key_source', 'dashboard')
            ->where('settings.public_key_source', 'dashboard')
            ->where('settings.webhook_secret_source', 'dashboard'))
        ->assertDontSee($keys['secret_key'])
        ->assertDontSee($keys['webhook_secret']);

    Http::fake([
        'api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/test'],
        ]),
    ]);

    app(ChapaService::class)->initializePayment(['tx_ref' => 'NF-test-admin-config']);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.$keys['secret_key']));
});

it('verifies webhook signatures with the saved secret and both documented header forms', function () {
    $this->actingAs(chapaAdmin())->put('/admin/chapa-settings', chapaTestKeys());
    $secret = chapaTestKeys()['webhook_secret'];
    $body = json_encode(['event' => 'charge.success', 'tx_ref' => 'NF-configuration-test']);

    $request = Request::create('/webhooks/chapa', 'POST', [], [], [], [], $body);
    $request->headers->set('x-chapa-signature', hash_hmac('sha256', $body, $secret));
    expect(app(ChapaService::class)->validateWebhook($request))->toBeTrue();

    $request->headers->remove('x-chapa-signature');
    $request->headers->set('chapa-signature', hash_hmac('sha256', $secret, $secret));
    expect(app(ChapaService::class)->validateWebhook($request))->toBeTrue();

    $request->headers->set('chapa-signature', hash_hmac('sha256', $body, 'wrong-secret'));
    expect(app(ChapaService::class)->validateWebhook($request))->toBeFalse();
});

it('keeps existing keys when update fields are left blank', function () {
    $admin = chapaAdmin();
    $keys = chapaTestKeys();
    $this->actingAs($admin)->put('/admin/chapa-settings', $keys);

    $this->actingAs($admin)->put('/admin/chapa-settings', [
        'mode' => 'test',
        'public_key' => '',
        'secret_key' => '',
        'webhook_secret' => '',
    ])->assertSessionHas('success');

    $setting = ChapaPaymentSetting::findOrFail(1);
    expect($setting->secret_key)->toBe($keys['secret_key'])
        ->and($setting->public_key)->toBe($keys['public_key'])
        ->and($setting->webhook_secret)->toBe($keys['webhook_secret']);
});

it('rejects cross-mode credentials and invalid switches without flashing secrets', function () {
    $admin = chapaAdmin();

    $this->actingAs($admin)->put('/admin/chapa-settings', [
        ...chapaTestKeys(),
        'secret_key' => 'CHASECK_LIVE-wrong-mode-secret',
    ])->assertSessionHasErrors('secret_key');

    expect(ChapaPaymentSetting::count())->toBe(0);

    $this->actingAs($admin)->put('/admin/chapa-settings', [
        'mode' => 'live',
        'public_key' => '',
        'secret_key' => '',
        'webhook_secret' => '',
    ])->assertSessionHasErrors('mode');

    $this->actingAs($admin)->put('/admin/chapa-settings', [
        'mode' => 'test',
        'public_key' => 'CHAPUBK_TEST-safe-value',
        'secret_key' => 'NOT_A_CHAPA_KEY',
        'webhook_secret' => 'webhook-secret-sample',
    ])->assertSessionHasErrors('secret_key')
        ->assertSessionMissing('_old_input.secret_key')
        ->assertSessionMissing('_old_input.webhook_secret');

    expect(ChapaPaymentSetting::count())->toBe(0);
});

it('switches to live only with a complete matching set and reports live mode', function () {
    $admin = chapaAdmin();
    $this->actingAs($admin)->put('/admin/chapa-settings', chapaTestKeys());

    $this->actingAs($admin)->put('/admin/chapa-settings', [
        'mode' => 'live',
        'public_key' => 'CHAPUBK_LIVE-public-example',
        'secret_key' => 'CHASECK_LIVE-private-example',
        'webhook_secret' => 'numan-live-webhook-secret-example',
    ])->assertSessionHas('success');

    expect(app(ChapaConfiguration::class)->mode())->toBe('live')
        ->and(app(ChapaConfiguration::class)->value('secretKey'))->toBe('CHASECK_LIVE-private-example')
        ->and(ChapaPaymentSetting::findOrFail(1)->mode)->toBe('live');
});

it('keeps environment credentials usable until dashboard overrides are saved', function () {
    config()->set('chapa.secretKey', 'CHASECK_TEST-fallback-key');
    config()->set('chapa.webhookSecret', 'environment-fallback-webhook');

    expect(app(ChapaConfiguration::class)->value('secretKey'))->toBe('CHASECK_TEST-fallback-key')
        ->and(app(ChapaConfiguration::class)->value('webhookSecret'))->toBe('environment-fallback-webhook');
});

it('accepts a signed real webhook HTTP request with the dashboard secret and rejects invalid signatures', function () {
    $this->actingAs(chapaAdmin())->put('/admin/chapa-settings', chapaTestKeys());
    $this->app['auth']->logout();

    $payload = [
        'event' => 'charge.failed',
        'status' => 'failed',
        'tx_ref' => 'NF-admin-webhook-configuration-test',
    ];
    $body = json_encode($payload);
    $secret = chapaTestKeys()['webhook_secret'];

    $this->call(
        'POST',
        '/webhooks/chapa',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CHAPA_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ],
        $body,
    )->assertSuccessful()->assertJsonPath('message', 'Event ignored');

    $this->call(
        'POST',
        '/webhooks/chapa',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CHAPA_SIGNATURE' => hash_hmac('sha256', $body, 'incorrect'),
        ],
        $body,
    )->assertStatus(403);
});

it('immediately rotates webhook verification without returning secrets to the browser', function () {
    $admin = chapaAdmin();
    $this->actingAs($admin)->put('/admin/chapa-settings', chapaTestKeys());

    $newSecret = 'numan-rotated-webhook-secret-987654321';
    $this->actingAs($admin)->put('/admin/chapa-settings', [
        'mode' => 'test',
        'public_key' => '',
        'secret_key' => '',
        'webhook_secret' => $newSecret,
    ])->assertSessionHas('success');

    $body = json_encode(['tx_ref' => 'NF-rotated', 'event' => 'charge.failed']);
    $request = Request::create('/webhooks/chapa', 'POST', [], [], [], [], $body);

    $request->headers->set(
        'x-chapa-signature',
        hash_hmac('sha256', $body, chapaTestKeys()['webhook_secret']),
    );
    expect(app(ChapaService::class)->validateWebhook($request))->toBeFalse();

    $request->headers->set(
        'x-chapa-signature',
        hash_hmac('sha256', $body, $newSecret),
    );
    expect(app(ChapaService::class)->validateWebhook($request))->toBeTrue();
});
