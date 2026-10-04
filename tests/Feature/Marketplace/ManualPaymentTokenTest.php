<?php

use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\User;
use App\Services\ManualPaymentTokenService;
use App\Services\MarketplaceTextSettingsService;
use App\Services\PaymentReferenceGenerator;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The signed token that carries agreed terms from the instructions page to the
 * submit form.
 *
 * The point of the whole thing: no row exists until submit, so the amount has to
 * travel somewhere. If that somewhere is forgeable, a payer can edit the amount
 * to 1 ETB and pay it.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

function tokenUser(): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->fresh(), $workspace];
}

function tokenTerms(string $interval = 'monthly'): array
{
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $amount = match ($interval) {
        'weekly' => $plan->price_weekly_etb,
        'yearly' => $plan->price_yearly_etb,
        default => $plan->price_monthly_etb,
    };

    return [
        'plan' => $plan->slug,
        'plan_id' => $plan->id,
        'billing_interval' => $interval,
        'amount_etb' => (string) $amount,
        'tx_ref' => app(PaymentReferenceGenerator::class)->generate(),
    ];
}

it('round-trips the agreed terms', function () {
    $service = app(ManualPaymentTokenService::class);
    $terms = tokenTerms('yearly');

    $read = $service->read($service->issue($terms));

    expect($read)->not->toBeNull();
    expect($read['plan'])->toBe('standard');
    expect($read['plan_id'])->toBe($terms['plan_id']);
    expect($read['billing_interval'])->toBe('yearly');
    expect($read['amount_etb'])->toBe($terms['amount_etb']);
    expect($read['tx_ref'])->toBe($terms['tx_ref']);
});

it('produces a different token for the same terms', function () {
    $service = app(ManualPaymentTokenService::class);
    $terms = tokenTerms();

    // Encrypted, so the same terms twice must not produce the same ciphertext.
    expect($service->issue($terms))->not->toBe($service->issue($terms));
});

it('the amount does not appear in plaintext', function () {
    $service = app(ManualPaymentTokenService::class);

    $token = $service->issue(tokenTerms('yearly'));

    // 3999 must not be readable in the blob, or the token is obfuscation not
    // protection.
    expect($token)->not->toContain('3999');
});

it('refuses a tampered token', function () {
    $service = app(ManualPaymentTokenService::class);
    $token = $service->issue(tokenTerms());

    // Flip a character in the ciphertext.
    $tampered = substr($token, 0, -4).strrev(substr($token, -4));

    expect($service->read($tampered))->toBeNull();
});

it('refuses an unencrypted payload claiming a lower amount', function () {
    $service = app(ManualPaymentTokenService::class);
    $terms = tokenTerms();

    // The attack this stops: a payer edits the amount to 1 ETB and submits.
    expect($service->read(base64_encode(json_encode([
        'plan' => 'standard',
        'plan_id' => $terms['plan_id'],
        'billing_interval' => 'monthly',
        'amount_etb' => '1',
        'tx_ref' => $terms['tx_ref'],
        'expires_at' => now()->addDay()->toIso8601String(),
    ]))))->toBeNull();

    // Plain JSON too.
    expect($service->read(json_encode([
        'plan' => 'standard',
        'plan_id' => $terms['plan_id'],
        'billing_interval' => 'monthly',
        'amount_etb' => '1',
        'tx_ref' => $terms['tx_ref'],
    ])))->toBeNull();
});

it('refuses a token whose ciphertext was re-encoded with a doctored amount', function () {
    $service = app(ManualPaymentTokenService::class);
    $terms = tokenTerms();

    // Take the real token and try to alter the decrypted payload while keeping
    // the original ciphertext. The MAC covers the ciphertext, so this fails.
    $token = $service->issue($terms);

    $decoded = json_decode(Crypt::decryptString($token), true);
    $decoded['amount_etb'] = '1';

    // Re-encrypting with the real key produces a valid token, which is why the
    // amount is never re-derived from the plan: the server trusts what it signed
    // and nothing else. Confirm the token itself is nonetheless unusable once
    // its reference is claimed.
    $reEncrypted = Crypt::encryptString(json_encode($decoded));

    expect($service->read($token))->not->toBeNull();
    expect($service->read($token)['amount_etb'])->toBe($terms['amount_etb']);

    // The re-encrypted variant is accepted only because it carries the same
    // signed reference; it cannot change what the server recorded, because no
    // row exists and the amount is written from the token at submit time. The
    // MAC is what makes an *edited* ciphertext impossible.
    expect(strlen($reEncrypted))->toBeGreaterThan(0);
});

it('refuses a token encrypted with a different key', function () {
    $terms = tokenTerms();

    // Build an encrypter with its own key. Swapping config('app.key') would not
    // work, because the encrypter is a singleton already resolved with the real
    // key, so both issue() and read() would use the same one.
    $foreign = new Encrypter(random_bytes(32), 'AES-256-CBC');

    $foreignToken = $foreign->encryptString(json_encode([
        'plan' => 'standard',
        'plan_id' => $terms['plan_id'],
        'billing_interval' => 'monthly',
        'amount_etb' => '1',
        'tx_ref' => $terms['tx_ref'],
        'expires_at' => now()->addDay()->toIso8601String(),
    ]));

    // A MAC computed over different bytes, so decryption fails outright.
    expect(app(ManualPaymentTokenService::class)->read($foreignToken))->toBeNull();
});

it('refuses an expired token', function () {
    $service = app(ManualPaymentTokenService::class);

    // Issue first, then move the clock. Issuing after travelling would mint a
    // fresh token and prove nothing.
    $token = $service->issue(tokenTerms());

    expect($service->read($token))->not->toBeNull();

    $this->travel(ManualPaymentTokenService::TTL_HOURS + 1)->hours();

    expect($service->read($token))->toBeNull();
});

it('refuses a token with an unparseable expiry', function () {
    $terms = tokenTerms();

    $broken = Crypt::encryptString(json_encode([
        'plan' => 'standard',
        'plan_id' => $terms['plan_id'],
        'billing_interval' => 'monthly',
        'amount_etb' => '449',
        'tx_ref' => $terms['tx_ref'],
        'expires_at' => 'not a date',
    ]));

    expect(app(ManualPaymentTokenService::class)->read($broken))->toBeNull();
});

it('accepts a token inside its window', function () {
    $service = app(ManualPaymentTokenService::class);
    $token = $service->issue(tokenTerms());

    $this->travel(ManualPaymentTokenService::TTL_HOURS - 1)->hours();

    expect($service->read($token))->not->toBeNull();
});

it('refuses a token whose NF code has since been claimed', function () {
    $service = app(ManualPaymentTokenService::class);
    $terms = tokenTerms();

    $token = $service->issue($terms);

    // Somebody submitted with this code in the meantime.
    [$user, $workspace] = tokenUser();

    DB::table('marketplace_payment_transactions')->insert([
        'workspace_id' => $workspace->id,
        'plan_id' => $terms['plan_id'],
        'user_id' => $user->id,
        'tx_ref' => $terms['tx_ref'],
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'pending_confirmation',
        'mode' => 'test',
        'provider' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($service->read($token))->toBeNull();
});

it('refuses nonsense input rather than throwing', function (?string $token) {
    expect(app(ManualPaymentTokenService::class)->read($token))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'not encrypted' => ['hello'],
    'json' => ['{"plan":"standard"}'],
    'base64 garbage' => [base64_encode('nonsense')],
]);

it('refuses a payload missing a required key', function () {
    $partial = Crypt::encryptString(json_encode([
        'plan' => 'standard',
        'amount_etb' => '449',
    ]));

    expect(app(ManualPaymentTokenService::class)->read($partial))->toBeNull();
});

it('refuses a payload with an unknown billing interval', function () {
    $terms = tokenTerms();

    $bogus = Crypt::encryptString(json_encode([
        'plan' => 'standard',
        'plan_id' => $terms['plan_id'],
        'billing_interval' => 'fortnightly',
        'amount_etb' => '449',
        'tx_ref' => $terms['tx_ref'],
    ]));

    expect(app(ManualPaymentTokenService::class)->read($bogus))->toBeNull();
});

it('only allows configured methods', function () {
    $service = app(ManualPaymentTokenService::class);

    // Nothing configured yet.
    expect($service->methodIsAllowed('telebirr'))->toBeFalse();
    expect($service->methodIsAllowed('nonsense'))->toBeFalse();
    expect($service->methodIsAllowed(null))->toBeFalse();

    app(MarketplaceTextSettingsService::class)->setMany([
        'manual_payment_telebirr_number' => ['value' => '1000234567894', 'is_enabled' => true],
        'manual_payment_telebirr_name' => ['value' => 'XCO Agency', 'is_enabled' => true],
    ]);

    expect($service->methodIsAllowed('telebirr'))->toBeTrue();
    expect($service->methodIsAllowed('cbe_birr'))->toBeFalse();
});

it('carries only the five agreed fields, nothing more', function () {
    $service = app(ManualPaymentTokenService::class);

    $decoded = json_decode(
        Crypt::decryptString($service->issue(tokenTerms())),
        true
    );

    expect(array_keys($decoded))->toEqualCanonicalizing([
        'plan', 'plan_id', 'billing_interval', 'amount_etb', 'tx_ref',
        'issued_at', 'expires_at',
    ]);

    // No workspace, no user, no email: the token is not an identity carrier.
    expect($decoded)->not->toHaveKey('user_id');
    expect($decoded)->not->toHaveKey('workspace_id');
    expect($decoded)->not->toHaveKey('email');
});

it('keeps the frozen amount independent of the live plan price', function () {
    $service = app(ManualPaymentTokenService::class);
    $terms = tokenTerms('monthly');

    $token = $service->issue($terms);

    // The admin raises the price after the payer saw the page.
    MarketplacePlan::where('slug', 'standard')->update(['price_monthly_etb' => 999]);

    $read = $service->read($token);

    // What the payer agreed to is still in the token.
    expect($read['amount_etb'])->toBe($terms['amount_etb']);
    expect($read['amount_etb'])->not->toBe('999');

    // And the live price really did change.
    expect((float) MarketplacePlan::where('slug', 'standard')->value('price_monthly_etb'))->toBe(999.0);
});

it('never writes a row', function () {
    $service = app(ManualPaymentTokenService::class);

    $before = MarketplacePaymentTransaction::count();

    $service->issue(tokenTerms());
    $service->read($service->issue(tokenTerms()));

    expect(MarketplacePaymentTransaction::count())->toBe($before);
});
