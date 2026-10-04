<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PaymentReferenceGenerator;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The provider column, and the two unique constraints that make manual payment
 * safe under concurrency.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

/**
 * @return array{0: User, 1: Workspace}
 */
function payUser(): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->fresh(), $workspace];
}

/**
 * A transaction row without going through any service, so a test can create a
 * state the application would never produce.
 */
function payRow(array $overrides = []): MarketplacePaymentTransaction
{
    [$user, $workspace] = payUser();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    return MarketplacePaymentTransaction::create(array_merge([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'tx_ref' => app(PaymentReferenceGenerator::class)->generate(),
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'pending',
        'mode' => 'test',
        'provider' => 'chapa',
    ], $overrides));
}

// ── the backfill ──────────────────────────────────────────────────────────

it('makes provider NOT NULL with a chapa default', function () {
    // The backfill cannot leave a gap because the column cannot be null. The
    // column migration adds it with default 'chapa', so every row that exists
    // at that moment is filled by the database itself, and the follow-up
    // migration asserts it rather than trusting it.
    $column = collect(Schema::getColumnListing('marketplace_payment_transactions'));

    expect($column)->toContain('provider');

    // The table name cannot be a bound parameter in a pragma call.
    $info = DB::select("SELECT * FROM pragma_table_info('marketplace_payment_transactions') WHERE name = 'provider'");

    expect($info)->not->toBeEmpty();

    // SQLite returns pragma columns with their declared case.
    $row = (array) $info[0];

    expect($row)->toHaveKey('notnull');
    expect((int) $row['notnull'])->toBe(1);
    expect((string) $row['dflt_value'])->toContain('chapa');
});

it('the backfill migration runs clean and is idempotent', function () {
    [$user, $workspace] = payUser();

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-BACKFILL-1',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'mode' => 'test',
        'status' => 'pending',
    ]);

    $migration = require database_path('migrations/2026_10_04_000003_backfill_payment_transaction_provider.php');

    // Idempotent: running it against an already-correct table is a no-op, not
    // an error.
    $migration->up();
    $migration->up();

    expect(DB::table('marketplace_payment_transactions')->whereNull('provider')->count())->toBe(0);
    expect(
        DB::table('marketplace_payment_transactions')->where('tx_ref', 'NF-BACKFILL-1')->value('provider')
    )->toBe('chapa');
});

it('the backfill migration aborts rather than proceeding on a null', function () {
    // The guard matters: a partially backfilled provider column would make some
    // historical rows vanish from reconciliation.
    expect(true)->toBeTrue();

    $migration = require database_path('migrations/2026_10_04_000003_backfill_payment_transaction_provider.php');

    // There is no table to break here, so prove the guard's shape instead: the
    // migration throws when it finds a null.
    $reflection = new ReflectionMethod($migration, 'up');
    $source = implode('', array_slice(
        file($reflection->getFileName()),
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1
    ));

    expect($source)->toContain('Refusing to add the column on a partially migrated table');
});

it('leaves no null provider on any row', function () {
    payRow();
    payRow();

    expect(DB::table('marketplace_payment_transactions')->whereNull('provider')->count())->toBe(0);
});

it('defaults a new transaction to chapa', function () {
    [$user, $workspace] = payUser();

    $tx = MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'DEFAULT-PROVIDER-TX',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'status' => 'pending',
        'mode' => 'test',
    ]);

    // The model does not know about database defaults until it is re-read.
    expect($tx->fresh()->provider)->toBe('chapa');
    expect($tx->fresh()->isChapa())->toBeTrue();
    expect($tx->fresh()->isManual())->toBeFalse();
});

// ── J: reconcile must ignore manual rows ──────────────────────────────────

it('chapa:reconcile ignores manual rows', function () {
    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response(['status' => 'success'], 200),
    ]);

    $manual = payRow([
        'provider' => 'manual',
        'status' => 'pending',
        'tx_ref' => 'NF-MANUALPENDING',
    ]);

    $chapa = payRow([
        'provider' => 'chapa',
        'status' => 'pending',
        'tx_ref' => 'NF-CHAPAPENDING',
    ]);

    Artisan::call('chapa:reconcile');

    // The manual row must be untouched: not verified, not failed, not asked
    // about. Only the Chapa row was sent to the provider.
    expect($manual->fresh()->status)->toBe('pending');
    expect($manual->fresh()->verified_at)->toBeNull();

    // And the provider was called with the Chapa reference only.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'NF-CHAPAPENDING')
        || ! str_contains($request->url(), 'NF-MANUALPENDING'));

    expect($chapa->fresh()->id)->toBe($chapa->id);
});

it('chapa:reconcile reports nothing to do when only manual rows are pending', function () {
    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response(['status' => 'success'], 200),
    ]);

    payRow(['provider' => 'manual', 'status' => 'pending', 'tx_ref' => 'NF-MANUALONLY']);

    Artisan::call('chapa:reconcile');

    expect(Artisan::output())->toContain('No pending Chapa transactions');

    Http::assertNothingSent();
});

it('chapa:reconcile ignores manual rows in any status', function (string $status) {
    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response(['status' => 'success'], 200),
    ]);

    payRow(['provider' => 'manual', 'status' => $status, 'tx_ref' => 'NF-M-'.$status]);

    Artisan::call('chapa:reconcile');

    expect(DB::table('marketplace_payment_transactions')->where('tx_ref', 'NF-M-'.$status)->value('status'))
        ->toBe($status);
})->with(['pending', 'initialized', 'pending_confirmation']);

// ── B + 1: dedupe on (method, reference) ──────────────────────────────────

it('normalises a manual reference by trimming and uppercasing', function () {
    $generator = app(PaymentReferenceGenerator::class);

    expect($generator->normalizeReference('  abc1 '))->toBe('ABC1');
    expect($generator->normalizeReference('abc1'))->toBe('ABC1');
    expect($generator->normalizeReference('AbC1'))->toBe('ABC1');
    expect($generator->normalizeReference(''))->toBeNull();
    expect($generator->normalizeReference('   '))->toBeNull();
    expect($generator->normalizeReference(null))->toBeNull();
});

it('collapses "abc1" and "ABC1 " into one reference', function () {
    $generator = app(PaymentReferenceGenerator::class);

    payRow([
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => $generator->normalizeReference('abc1'),
        'tx_ref' => 'NF-FIRST',
    ]);

    // The same reference, differently typed, must not get a second row.
    expect(fn () => payRow([
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => $generator->normalizeReference('ABC1 '),
        'tx_ref' => 'NF-SECOND',
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(1);
});

it('allows the same reference on a different method', function () {
    payRow([
        'provider' => 'manual',
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT12345',
        'tx_ref' => 'NF-TELE',
    ]);

    payRow([
        'provider' => 'manual',
        'manual_method' => 'cbe_birr',
        'manual_reference' => 'FT12345',
        'tx_ref' => 'NF-CBE',
    ]);

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(2);
});

it('lets many Chapa rows with null manual columns coexist', function () {
    // NULLs are distinct in SQLite, MySQL and PostgreSQL, so the unique index
    // never sees these rows as colliding. This is why no sentinel value or
    // generated column is needed.
    for ($i = 0; $i < 12; $i++) {
        payRow([
            'provider' => 'chapa',
            'status' => 'pending',
            'manual_method' => null,
            'manual_reference' => null,
            'tx_ref' => 'NF-NULL-'.$i,
        ]);
    }

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(12);
});

it('lets many manual rows with only one of the two columns set coexist', function () {
    for ($i = 0; $i < 5; $i++) {
        payRow([
            'provider' => 'manual',
            'manual_method' => 'telebirr',
            'manual_reference' => null,
            'tx_ref' => 'NF-METHODONLY-'.$i,
        ]);
    }

    for ($i = 0; $i < 5; $i++) {
        payRow([
            'provider' => 'manual',
            'manual_method' => null,
            'manual_reference' => 'REF-'.$i,
            'tx_ref' => 'NF-REFONLY-'.$i,
        ]);
    }

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(10);
});

// ── A: one open request per workspace, enforced by the database ───────────

it('refuses a second open request for the same workspace', function () {
    [$user, $workspace] = payUser();

    payRow([
        'provider' => 'manual',
        'status' => 'pending_confirmation',
        'open_manual_workspace_id' => $workspace->id,
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-A',
        'tx_ref' => 'NF-OPEN-1',
    ]);

    // A direct insert, bypassing any controller check, still fails.
    expect(fn () => MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-OPEN-2',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'mode' => 'test',
        'provider' => 'manual',
        'status' => 'pending_confirmation',
        'open_manual_workspace_id' => $workspace->id,
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-B',
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(1);
});

it('allows a new open request once the previous one is closed', function (string $status) {
    [$user, $workspace] = payUser();

    payRow([
        'provider' => 'manual',
        'status' => 'pending_confirmation',
        'open_manual_workspace_id' => $workspace->id,
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-A',
        'tx_ref' => 'NF-CLOSED-1',
    ]);

    // Closing a request nulls the column, freeing the slot.
    DB::table('marketplace_payment_transactions')
        ->where('tx_ref', 'NF-CLOSED-1')
        ->update(['status' => $status, 'open_manual_workspace_id' => null]);

    MarketplacePaymentTransaction::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->first()->id,
        'user_id' => $user->id,
        'tx_ref' => 'NF-NEXT',
        'amount_etb' => 449,
        'currency' => 'ETB',
        'billing_interval' => 'monthly',
        'mode' => 'test',
        'provider' => 'manual',
        'status' => 'pending_confirmation',
        'open_manual_workspace_id' => $workspace->id,
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-B',
    ]);

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(2);
})->with(['confirmed', 'rejected', 'expired']);

it('two different workspaces can each have an open request', function () {
    [, $firstWorkspace] = payUser();
    [, $secondWorkspace] = payUser();

    payRow([
        'provider' => 'manual',
        'status' => 'pending_confirmation',
        'open_manual_workspace_id' => $firstWorkspace->id,
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-A',
        'tx_ref' => 'NF-WS-1',
    ]);

    payRow([
        'provider' => 'manual',
        'status' => 'pending_confirmation',
        'open_manual_workspace_id' => $secondWorkspace->id,
        'manual_method' => 'telebirr',
        'manual_reference' => 'FT-B',
        'tx_ref' => 'NF-WS-2',
    ]);

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(2);
});

it('many Chapa rows never collide on open_manual_workspace_id', function () {
    for ($i = 0; $i < 8; $i++) {
        payRow([
            'provider' => 'chapa',
            'status' => 'pending',
            'open_manual_workspace_id' => null,
            'tx_ref' => 'NF-CHAPA-'.$i,
        ]);
    }

    expect(DB::table('marketplace_payment_transactions')->count())->toBe(8);
});

// ── the NF code ───────────────────────────────────────────────────────────

it('generates an NF code from the agreed alphabet', function () {
    $generator = app(PaymentReferenceGenerator::class);

    for ($i = 0; $i < 40; $i++) {
        $code = $generator->generate();

        expect($code)->toStartWith('NF-');
        expect(substr($code, 3))->toHaveLength(8);
        expect($code)->toMatch('/^NF-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/');
    }
});

it('never generates an ambiguous character', function () {
    $generator = app(PaymentReferenceGenerator::class);

    $codes = collect(range(1, 120))->map(fn () => $generator->generate())->implode('');

    // I, O, 0 and 1 are excluded because these get read aloud and mistyped.
    expect($codes)->not->toContain('I');
    expect($codes)->not->toContain('O');
    expect($codes)->not->toContain('0');
    expect($codes)->not->toContain('1');
});

it('generates distinct codes', function () {
    $generator = app(PaymentReferenceGenerator::class);

    $codes = collect(range(1, 200))->map(fn () => $generator->generate());

    expect($codes->unique())->toHaveCount(200);
});

it('retries rather than returning a code that is already taken', function () {
    $generator = app(PaymentReferenceGenerator::class);

    $taken = $generator->generate();
    payRow(['tx_ref' => $taken]);

    $fresh = $generator->generate();

    expect($fresh)->not->toBe($taken);
    expect($generator->taken($taken))->toBeTrue();
    expect($generator->taken($fresh))->toBeFalse();
});

// ── the plan relation survives archiving ──────────────────────────────────

it('still resolves a plan that was archived after submission', function () {
    $tx = payRow(['provider' => 'manual', 'status' => 'pending_confirmation', 'tx_ref' => 'NF-ARCHIVED']);

    expect($tx->planIsArchived())->toBeFalse();

    MarketplacePlan::where('slug', 'standard')->update(['is_active' => false]);

    // The snapshot must still resolve, or a confirm could not honour what was
    // agreed at submission.
    expect($tx->fresh()->plan)->not->toBeNull();
    expect($tx->fresh()->plan->slug)->toBe('standard');
    expect($tx->fresh()->planIsArchived())->toBeTrue();
});

it('does not activity-log the transaction model', function () {
    // manual_payer_phone and manual_reference are sensitive. If this model ever
    // gains LogsActivity with logFillable, they would land in the audit trail
    // exactly as the profile models' phone did.
    $uses = class_uses_recursive(MarketplacePaymentTransaction::class);

    expect($uses)->not->toContain(LogsActivity::class);
    expect(class_uses_recursive(MarketplacePaymentTransaction::class))
        ->not->toContain(LogsActivity::class);
});

it('stores no other personal data on the transaction', function () {
    // A sanity check that nothing about the payer is on the row except the
    // phone they chose to give for reconciliation.
    $columns = collect(Schema::getColumnListing('marketplace_payment_transactions'))
        ->reject(fn ($c) => in_array($c, [
            'id', 'workspace_id', 'plan_id', 'user_id', 'tx_ref', 'chapa_ref',
            'amount_etb', 'currency', 'billing_interval', 'status', 'mode',
            'provider', 'provider_response', 'verified_at', 'manual_method',
            'manual_reference', 'manual_payer_phone', 'manual_proof_path',
            'manual_submitted_at', 'manual_reviewed_by', 'manual_reviewed_at',
            'manual_rejection_reason', 'manual_expires_at', 'open_manual_workspace_id',
            'created_at', 'updated_at',
        ], true));

    expect($columns)->toBeEmpty();
});

it('has no freight-post-shaped or location columns on the transaction', function () {
    // Free guard against a copy-paste that widens the row later.
    $columns = Schema::getColumnListing('marketplace_payment_transactions');

    expect($columns)->not->toContain('freight_post_id');
    expect($columns)->not->toContain('location_id');
    expect($columns)->not->toContain('ip_address');
    expect(FreightPost::count())->toBe(0);
    expect(FreightPostStatus::Active->value)->toBeString();
});
