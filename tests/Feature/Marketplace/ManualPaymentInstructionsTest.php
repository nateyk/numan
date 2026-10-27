<?php

use App\Models\MarketplaceTextSetting;
use App\Models\Profile;
use App\Models\User;
use App\Services\MarketplaceTextSettingsService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The plans-page entry point, and the admin screen that decides whether it
 * exists at all.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

function instructionsAdmin(): User
{
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
        'is_superadmin' => true,
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($admin);
    $admin->update(['current_workspace_id' => $workspace->id]);
    Profile::firstOrCreate(['user_id' => $admin->id], [
        'phone' => '+251911000000',
    ]);

    return $admin->fresh();
}

function instructionsPayer(): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);
    Profile::firstOrCreate(['user_id' => $user->id], [
        'posts_freight' => true,
        'display_name' => 'Payer',
        'phone' => '+251911000111',
    ]);

    return $user->fresh();
}

function configureTelebirr(): void
{
    app(MarketplaceTextSettingsService::class)->setMany([
        'manual_payment_telebirr_number' => ['value' => '1000234567894', 'is_enabled' => true],
        'manual_payment_telebirr_name' => ['value' => 'XCO Agency', 'is_enabled' => true],
    ]);
}

// ── the plans page tells the truth about availability ─────────────────────

it('hides the entry point when nothing is configured', function () {
    $payer = instructionsPayer();

    $this->actingAs($payer)
        ->get(route('billing.plans'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('manualPayment.enabled', false)
            ->where('manualPayment.methods', fn ($m) => count($m) === 0)
        );
});

it('offers the entry point once a method is fully configured', function () {
    configureTelebirr();
    $payer = instructionsPayer();

    $this->actingAs($payer)
        ->get(route('billing.plans'))
        ->assertInertia(fn ($page) => $page
            ->where('manualPayment.enabled', true)
            ->where('manualPayment.methods.telebirr.label', 'Telebirr')
            ->where('manualPayment.methods.telebirr.fields.manual_payment_telebirr_number', '1000234567894')
            ->where('manualPayment.expiry_days', 14)
        );
});

it('does not offer a half-configured method', function () {
    app(MarketplaceTextSettingsService::class)->setMany([
        'manual_payment_telebirr_number' => ['value' => '1000234567894', 'is_enabled' => true],
        // Name missing entirely.
    ]);

    $payer = instructionsPayer();

    $this->actingAs($payer)
        ->get(route('billing.plans'))
        ->assertInertia(fn ($page) => $page->where('manualPayment.enabled', false));
});

it('hides the entry point again when a method is switched off', function () {
    configureTelebirr();
    $payer = instructionsPayer();

    $this->actingAs($payer)->get(route('billing.plans'))
        ->assertInertia(fn ($page) => $page->where('manualPayment.enabled', true));

    app(MarketplaceTextSettingsService::class)->setMany([
        'manual_payment_telebirr_number' => ['is_enabled' => false],
    ]);

    $this->actingAs($payer)->get(route('billing.plans'))
        ->assertInertia(fn ($page) => $page->where('manualPayment.enabled', false));
});

it('links to the manual request index', function () {
    configureTelebirr();
    $payer = instructionsPayer();

    $this->actingAs($payer)
        ->get(route('billing.plans'))
        ->assertInertia(fn ($page) => $page
            ->where('manualPayment.index_url', route('marketplace.manual.index'))
        );
});

// ── the admin screen ──────────────────────────────────────────────────────

it('shows the admin the current state', function () {
    configureTelebirr();

    $this->actingAs(instructionsAdmin())
        ->get(route('admin.marketplace-payment-instructions.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/marketplace-payment-instructions')
            ->where('enabled', true)
            ->where('expiryDays', 14)
            ->where('settings.manual_payment_telebirr_number.value', '1000234567894')
            ->where('settings.manual_payment_telebirr_number.is_enabled', true)
        );
});

it('lets an admin configure a method', function () {
    $admin = instructionsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payment-instructions.update'), [
            'manual_payment_telebirr_number' => '1000234567894',
            'manual_payment_telebirr_number_enabled' => true,
            'manual_payment_telebirr_name' => 'XCO Agency',
            'manual_payment_telebirr_name_enabled' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $service = app(MarketplaceTextSettingsService::class);

    expect($service->manualPaymentEnabled())->toBeTrue();
    expect($service->availableMethods()['telebirr']['fields']['manual_payment_telebirr_name'])->toBe('XCO Agency');
});

it('records who changed the instructions', function () {
    $admin = instructionsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payment-instructions.update'), [
            'manual_payment_telebirr_number' => '1000234567894',
            'manual_payment_telebirr_number_enabled' => true,
            'manual_payment_telebirr_name' => 'XCO Agency',
            'manual_payment_telebirr_name_enabled' => true,
        ]);

    expect(MarketplaceTextSetting::where('key', 'manual_payment_telebirr_number')->value('updated_by'))
        ->toBe($admin->id);
});

it('saves general instructions with their own enable flag', function () {
    $admin = instructionsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payment-instructions.update'), [
            'instructions' => 'Transfers settle within an hour.',
            'manual_payment_instructions_enabled' => true,
        ]);

    $service = app(MarketplaceTextSettingsService::class);

    expect($service->generalInstructions())->toBe('Transfers settle within an hour.');

    // Instructions alone do not switch manual payment on.
    expect($service->manualPaymentEnabled())->toBeFalse();
});

it('clearing a field takes the method out of circulation', function () {
    configureTelebirr();
    $admin = instructionsAdmin();

    expect(app(MarketplaceTextSettingsService::class)->manualPaymentEnabled())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payment-instructions.update'), [
            'manual_payment_telebirr_number' => '',
            'manual_payment_telebirr_number_enabled' => true,
            'manual_payment_telebirr_name' => 'XCO Agency',
            'manual_payment_telebirr_name_enabled' => true,
        ]);

    expect(app(MarketplaceTextSettingsService::class)->manualPaymentEnabled())->toBeFalse();
});

it('refuses a super-long instruction', function () {
    $admin = instructionsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payment-instructions.update'), [
            'instructions' => str_repeat('a', 5001),
        ])
        ->assertSessionHasErrors('instructions');
});

it('refuses the screen to a non-superadmin', function () {
    $payer = instructionsPayer();

    $this->actingAs($payer)
        ->get(route('admin.marketplace-payment-instructions.edit'))
        ->assertForbidden();

    $this->actingAs($payer)
        ->post(route('admin.marketplace-payment-instructions.update'), [
            'manual_payment_telebirr_number' => 'HACKED',
        ])
        ->assertForbidden();
});

it('refuses the screen to a guest', function () {
    $this->get(route('admin.marketplace-payment-instructions.edit'))
        ->assertRedirect(route('login'));
});

it('stores no secret beyond the account numbers meant to be shown', function () {
    $admin = instructionsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.marketplace-payment-instructions.update'), [
            'manual_payment_telebirr_number' => '1000234567894',
            'manual_payment_telebirr_number_enabled' => true,
            'manual_payment_telebirr_name' => 'XCO Agency',
            'manual_payment_telebirr_name_enabled' => true,
        ]);

    // Every seeded key is either a required field or the instructions blob.
    // There is deliberately nowhere to put an API key or a webhook secret.
    $keys = MarketplaceTextSetting::query()->pluck('key');

    expect($keys)->toHaveCount(8);

    foreach ($keys as $key) {
        expect($key)->toStartWith('manual_payment_');
    }

    $columns = Schema::getColumnListing('marketplace_text_settings');

    expect($columns)->not->toContain('secret');
    expect($columns)->not->toContain('api_key');
    expect($columns)->not->toContain('token');
});
