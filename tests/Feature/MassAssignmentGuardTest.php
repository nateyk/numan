<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplacePaymentReceipt;
use App\Models\MarketplacePaymentTransaction;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Guard against the mass-assignment bug class seen twice already:
 *   - marketplace_payment_transactions.billing_interval was written but not
 *     fillable, so every subscription silently defaulted to monthly.
 *   - freight_posts.hidden_at was the same mistake.
 *
 * Any column must be either mass-assignable, or explicitly declared here as
 * deliberately not mass-assignable. A new column in neither fails the suite.
 */
uses(RefreshDatabase::class);

/**
 * Columns that must never be mass-assignable, per table.
 *
 * These are framework-managed (surrogate key and timestamps) or handled by
 * dedicated behaviour. Adding an entry here is a deliberate decision.
 */
function nonFillableAllowlist(): array
{
    return [
        'marketplace_plans' => ['id', 'created_at', 'updated_at', 'deleted_at'],
        'marketplace_subscriptions' => ['id', 'created_at', 'updated_at'],
        'marketplace_payment_transactions' => ['id', 'created_at', 'updated_at'],
        'marketplace_payment_receipts' => ['id', 'created_at', 'updated_at'],
        'marketplace_settings' => ['id', 'created_at', 'updated_at'],
        'freight_posts' => ['id', 'created_at', 'updated_at', 'telegram_published_at'],
        'freight_interests' => ['id', 'created_at', 'updated_at'],
    ];
}

function guardedModels(): array
{
    return [
        'marketplace_plans' => MarketplacePlan::class,
        'marketplace_subscriptions' => MarketplaceSubscription::class,
        'marketplace_payment_transactions' => MarketplacePaymentTransaction::class,
        'marketplace_payment_receipts' => MarketplacePaymentReceipt::class,
        'marketplace_settings' => MarketplaceSetting::class,
        'freight_posts' => FreightPost::class,
        'freight_interests' => FreightInterest::class,
    ];
}

/**
 * Pest dataset rows: [table, model].
 *
 * @return array<string, array{string, class-string}>
 */
function guardedModelDataset(): array
{
    $rows = [];

    foreach (guardedModels() as $table => $model) {
        $rows[$table] = [$table, $model];
    }

    return $rows;
}

/**
 * Columns that are neither fillable nor allowlisted. Any entry is a bug.
 *
 * @param  list<string>  $columns
 * @param  list<string>  $fillable
 * @param  list<string>  $allowlist
 * @return list<string>
 */
function undeclaredColumns(array $columns, array $fillable, array $allowlist): array
{
    return array_values(array_diff($columns, $fillable, $allowlist));
}

it('declares an allowlist entry for every guarded table', function () {
    expect(array_keys(nonFillableAllowlist()))
        ->toEqual(array_keys(guardedModels()));
});

it('every column is fillable or explicitly allowlisted', function (string $table, string $model) {
    $columns = Schema::getColumnListing($table);
    $fillable = (new $model)->getFillable();
    $allowlist = nonFillableAllowlist()[$table] ?? [];

    $undeclared = undeclaredColumns($columns, $fillable, $allowlist);

    expect($undeclared)
        ->toBe([], sprintf(
            'Table %s has column(s) that are neither in $fillable nor in the non-fillable allowlist: %s. '
            .'Add the column to the model $fillable, or list it in nonFillableAllowlist() if it must not be mass-assignable.',
            $table,
            implode(', ', $undeclared)
        ));
})->with(guardedModelDataset());

it('the models use an explicit fillable whitelist rather than guarded=[]', function (string $table, string $model) {
    // A mass-assignment guard only holds if the model is not wide open.
    expect((new $model)->getGuarded())->toContain('*');
})->with(guardedModelDataset());

it('the allowlist only names columns that actually exist', function (string $table, string $model) {
    $columns = Schema::getColumnListing($table);

    $stale = array_values(array_diff(nonFillableAllowlist()[$table] ?? [], $columns));

    expect($stale)
        ->toBe([], sprintf(
            'nonFillableAllowlist() lists column(s) that no longer exist on %s: %s. Remove them.',
            $table,
            implode(', ', $stale)
        ));
})->with(guardedModelDataset());

it('detects a column added to neither fillable nor the allowlist', function () {
    // Proves the guard actually fails when a column lands in neither list.
    // This is the exact shape of the billing_interval and hidden_at bugs.
    $columns = ['id', 'created_at', 'updated_at', 'new_important_column'];
    $fillable = ['ulid', 'status'];
    $allowlist = ['id', 'created_at', 'updated_at'];

    expect(undeclaredColumns($columns, $fillable, $allowlist))
        ->toBe(['new_important_column']);
});

it('detects a second undeclared column', function () {
    $columns = ['id', 'a', 'b', 'c'];
    expect(undeclaredColumns($columns, ['a'], ['id', 'b']))
        ->toBe(['c']);
});

it('reports nothing when every column is declared', function () {
    $columns = ['id', 'created_at', 'updated_at', 'a', 'b'];
    expect(undeclaredColumns($columns, ['a', 'b'], ['id', 'created_at', 'updated_at']))
        ->toBe([]);
});

it('billing_interval is mass-assignable on the transaction', function () {
    // Regression guard for the bug that made every subscription monthly.
    expect((new MarketplacePaymentTransaction)->getFillable())
        ->toContain('billing_interval');
});

it('hidden_at is mass-assignable on the freight post', function () {
    // Regression guard for the bug that made hidden posts invisible to tests.
    expect((new FreightPost)->getFillable())->toContain('hidden_at');
});

it('writing an undeclared column silently does nothing', function () {
    // Documents why the guard matters: mass assignment drops unknown keys.
    $user = User::factory()->create();

    $post = FreightPost::create([
        'ulid' => 'ULIDTEST0001',
        'user_id' => $user->id,
        'status' => 'draft',
        // Not a real column: silently discarded rather than erroring.
        'not_a_real_column' => 'ignored',
    ]);

    expect($post->ulid)->toBe('ULIDTEST0001');
    expect(array_key_exists('not_a_real_column', $post->getAttributes()))->toBeFalse();
});
