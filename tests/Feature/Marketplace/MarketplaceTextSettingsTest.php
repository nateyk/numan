<?php

use App\Models\MarketplaceTextSetting;
use App\Models\User;
use App\Services\MarketplaceTextSettingsService;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Text settings, and the rule that decides whether manual payment is offered at
 * all.
 */
uses(RefreshDatabase::class);

/**
 * Configure exactly one method, leaving the other two untouched.
 */
function configureMethod(MarketplaceTextSettingsService $service, string $method, array $overrides = [], bool $enabled = true): void
{
    $fields = match ($method) {
        'telebirr' => [
            'manual_payment_telebirr_number' => '1000234567894',
            'manual_payment_telebirr_name' => 'XCO Agency',
        ],
        'cbe_birr' => [
            'manual_payment_cbe_birr_number' => '1000123456789',
            'manual_payment_cbe_birr_name' => 'XCO Agency PLC',
        ],
        'bank_transfer' => [
            'manual_payment_bank_transfer_number' => '1000987654321',
            'manual_payment_bank_transfer_name' => 'XCO Agency PLC',
            'manual_payment_bank_transfer_bank' => 'Commercial Bank of Ethiopia',
        ],
    };

    $values = [];

    foreach ($fields as $key => $value) {
        $values[$key] = ['value' => $value, 'is_enabled' => $enabled];
    }

    $service->setMany(array_merge($values, $overrides));
}

it('seeds the keys with no values and everything disabled', function () {
    $rows = MarketplaceTextSetting::query()->get();

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect($row->value)->toBeNull();
        expect($row->is_enabled)->toBeFalse();
        expect($row->label)->not->toBeEmpty();
    }
});

it('stores the keys it declares and no others', function () {
    $keys = MarketplaceTextSetting::query()->pluck('key')->sort()->values()->all();

    expect($keys)->toBe([
        'manual_payment_bank_transfer_bank',
        'manual_payment_bank_transfer_name',
        'manual_payment_bank_transfer_number',
        'manual_payment_cbe_birr_name',
        'manual_payment_cbe_birr_number',
        'manual_payment_instructions',
        'manual_payment_telebirr_name',
        'manual_payment_telebirr_number',
    ]);
});

it('keeps text intact, including a long paragraph and an account number', function () {
    $service = app(MarketplaceTextSettingsService::class);

    $paragraph = "Transfer to Telebirr 1000234567894.\nThen enter reference NF-ABC23456 in the note.\n"
        .'Wait for the confirmation SMS before submitting.';

    $service->setMany([
        'manual_payment_instructions' => ['value' => $paragraph, 'is_enabled' => true],
        'manual_payment_telebirr_number' => ['value' => '1000234567894', 'is_enabled' => true],
    ]);

    expect($service->get('manual_payment_instructions'))->toBe($paragraph);
    // A 13-digit account number must survive as a string, not become a float.
    expect($service->get('manual_payment_telebirr_number'))->toBe('1000234567894');
});

it('round-trips a value that the integer table could never hold', function () {
    $service = app(MarketplaceTextSettingsService::class);

    $service->setMany([
        'manual_payment_instructions' => ['value' => 'Telebirr, account name XCO Agency', 'is_enabled' => true],
    ]);

    // The integer table would have cast this to 0.
    expect($service->get('manual_payment_instructions'))->toBe('Telebirr, account name XCO Agency');
    expect(DB::table('marketplace_text_settings')->where('key', 'manual_payment_instructions')->value('value'))
        ->toBe('Telebirr, account name XCO Agency');
});

it('treats a blank value as not configured', function () {
    $service = app(MarketplaceTextSettingsService::class);

    $service->setMany(['manual_payment_telebirr_number' => ['value' => '1000234567894']]);
    expect($service->get('manual_payment_telebirr_number'))->toBe('1000234567894');

    $service->setMany(['manual_payment_telebirr_number' => ['value' => '   ']]);

    expect(MarketplaceTextSetting::where('key', 'manual_payment_telebirr_number')->value('value'))->toBeNull();
    expect($service->get('manual_payment_telebirr_number'))->toBeNull();
});

it('records who changed a setting', function () {
    $admin = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    app(WorkspaceService::class)->createPersonalWorkspace($admin);

    $service = app(MarketplaceTextSettingsService::class);
    $service->setMany(['manual_payment_telebirr_number' => ['value' => '1000234567894']], $admin->id);

    $row = MarketplaceTextSetting::where('key', 'manual_payment_telebirr_number')->first();

    expect($row->updated_by)->toBe($admin->id);
    expect($row->editor)->not->toBeNull();
});

// ── the availability rule ─────────────────────────────────────────────────

it('offers nothing when nothing is configured', function () {
    $service = app(MarketplaceTextSettingsService::class);

    expect($service->availableMethods())->toBe([]);
    expect($service->manualPaymentEnabled())->toBeFalse();
});

it('offers a method once every field is set and enabled', function () {
    $service = app(MarketplaceTextSettingsService::class);

    configureMethod($service, 'telebirr');

    expect($service->manualPaymentEnabled())->toBeTrue();
    expect(array_keys($service->availableMethods()))->toBe(['telebirr']);
    expect($service->methodIsAvailable('telebirr'))->toBeTrue();
});

it('withholds a method missing any one required field', function (string $method, string $missingKey) {
    $service = app(MarketplaceTextSettingsService::class);

    configureMethod($service, $method);

    // Blank exactly one required field for that method.
    $service->setMany([$missingKey => ['value' => '']]);

    expect($service->methodIsAvailable($method))->toBeFalse();
    expect($service->availableMethods())->toBe([]);
    expect($service->manualPaymentEnabled())->toBeFalse();
})->with([
    'telebirr number' => ['telebirr', 'manual_payment_telebirr_number'],
    'telebirr name' => ['telebirr', 'manual_payment_telebirr_name'],
    'cbe number' => ['cbe_birr', 'manual_payment_cbe_birr_number'],
    'cbe name' => ['cbe_birr', 'manual_payment_cbe_birr_name'],
    'transfer number' => ['bank_transfer', 'manual_payment_bank_transfer_number'],
    'transfer name' => ['bank_transfer', 'manual_payment_bank_transfer_name'],
    'transfer bank' => ['bank_transfer', 'manual_payment_bank_transfer_bank'],
]);

it('withholds a method whose fields are filled but not enabled', function () {
    $service = app(MarketplaceTextSettingsService::class);

    configureMethod($service, 'cbe_birr', [
        'manual_payment_cbe_birr_number' => ['value' => '1000123456789', 'is_enabled' => false],
    ]);

    expect($service->methodIsAvailable('cbe_birr'))->toBeFalse();
    expect($service->manualPaymentEnabled())->toBeFalse();
});

it('offers each configured method independently', function () {
    $service = app(MarketplaceTextSettingsService::class);

    configureMethod($service, 'telebirr');
    configureMethod($service, 'cbe_birr');

    expect(array_keys($service->availableMethods()))->toBe(['telebirr', 'cbe_birr']);
    expect($service->manualPaymentEnabled())->toBeTrue();
});

it('exposes the configured fields for a method', function () {
    $service = app(MarketplaceTextSettingsService::class);

    configureMethod($service, 'bank_transfer');

    $method = $service->availableMethods()['bank_transfer'];

    expect($method['label'])->toBe('Bank transfer');
    expect($method['fields']['manual_payment_bank_transfer_number'])->toBe('1000987654321');
    expect($method['fields']['manual_payment_bank_transfer_bank'])->toBe('Commercial Bank of Ethiopia');
});

it('hides general instructions unless enabled', function () {
    $service = app(MarketplaceTextSettingsService::class);

    $service->setMany(['manual_payment_instructions' => ['value' => 'Wait for the SMS.', 'is_enabled' => false]]);
    expect($service->generalInstructions())->toBeNull();

    $service->setMany(['manual_payment_instructions' => ['is_enabled' => true]]);
    expect($service->generalInstructions())->toBe('Wait for the SMS.');
});

it('general instructions alone do not enable manual payment', function () {
    $service = app(MarketplaceTextSettingsService::class);

    $service->setMany(['manual_payment_instructions' => ['value' => 'Text us on 0911.', 'is_enabled' => true]]);

    expect($service->generalInstructions())->not->toBeNull();
    expect($service->manualPaymentEnabled())->toBeFalse();
});

// ── structure ─────────────────────────────────────────────────────────────

it('keeps text settings in their own table, not the integer one', function () {
    $service = app(MarketplaceTextSettingsService::class);

    configureMethod($service, 'telebirr');

    // Nothing leaked into the integer table, where it would have been cast to 0.
    expect(DB::table('marketplace_settings')->where('key', 'like', '%text%')->count())->toBe(0);
    expect(DB::table('marketplace_text_settings')->whereNotNull('value')->count())->toBeGreaterThan(0);
});

it('the text table has no integer-only columns', function () {
    $columns = Schema::getColumnListing('marketplace_text_settings');

    expect($columns)->toContain('value');
    expect($columns)->toContain('is_enabled');
    expect($columns)->not->toContain('type');
});

it('declares a required field set for every method', function () {
    $service = app(MarketplaceTextSettingsService::class);

    foreach ($service::knownMethods() as $method) {
        expect($service::REQUIRED_FIELDS)->toHaveKey($method);
        expect($service::REQUIRED_FIELDS[$method])->not->toBeEmpty();
    }
});

it('returns empty rather than throwing before the table exists', function () {
    Schema::drop('marketplace_text_settings');

    $service = app(MarketplaceTextSettingsService::class);

    expect($service->all())->toBe([]);
    expect($service->availableMethods())->toBe([]);
    expect($service->manualPaymentEnabled())->toBeFalse();
});

it('every seeded key is either a required field or the general instructions', function () {
    // A seeded key nothing reads is dead config; a required key with no seed is
    // a method that can never be offered. Both are mistakes worth catching.
    $required = collect(MarketplaceTextSettingsService::REQUIRED_FIELDS)
        ->flatten()
        ->push('manual_payment_instructions')
        ->sort()
        ->values()
        ->all();

    $seeded = MarketplaceTextSetting::query()->pluck('key')->sort()->values()->all();

    expect($seeded)->toBe($required);
});

it('gives every seeded row a distinct, non-empty label', function () {
    $labels = MarketplaceTextSetting::query()->pluck('label');

    expect($labels->filter()->count())->toBe($labels->count());
    expect($labels->unique()->count())->toBe($labels->count());
});
