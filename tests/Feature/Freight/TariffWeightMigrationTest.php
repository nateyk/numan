<?php

use App\Models\FreightPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tariff-weight migration and the item_name backfill that ships with it.
 *
 * The migration is deliberately conditional, so these cover both worlds: an
 * environment where the columns already existed (they do in development, from an
 * earlier differently named file) and one where it has to create them.
 */
uses(RefreshDatabase::class);

/**
 * Re-run the migration the way artisan would.
 */
function runTariffWeightMigration(): void
{
    $migration = require database_path('migrations/2026_10_05_000001_add_tariff_weight_to_freight_posts_table.php');

    $migration->up();
}

it('adds the tariff weight columns', function () {
    expect(Schema::hasColumn('freight_posts', 'tariff_weight'))->toBeTrue()
        ->and(Schema::hasColumn('freight_posts', 'tariff_weight_unit'))->toBeTrue();
});

it('is a no-op on an environment where the columns already exist', function () {
    // The whole reason for the hasColumn guard: development already had these
    // columns from an earlier migration with a different name. Re-running must
    // not fail, or every subsequent deploy would break.
    runTariffWeightMigration();

    expect(Schema::hasColumn('freight_posts', 'tariff_weight'))->toBeTrue();
});

it('backfills item_name from cargo_description where item_name is empty', function () {
    $post = FreightPost::factory()->create([
        'cargo_description' => '20 tons teff in 40kg bags, palletized',
        'item_name' => null,
    ]);

    runTariffWeightMigration();

    expect($post->fresh()->item_name)->toBe('20 tons teff in 40kg bags, palletized');
});

it('never overwrites an item_name the user has already typed', function () {
    $post = FreightPost::factory()->create([
        'cargo_description' => 'the old description',
        'item_name' => 'Teff',
    ]);

    runTariffWeightMigration();

    expect($post->fresh()->item_name)->toBe('Teff');
});

it('truncates a backfilled item_name to the column width', function () {
    $post = FreightPost::factory()->create([
        'cargo_description' => str_repeat('a', 400),
        'item_name' => null,
    ]);

    runTariffWeightMigration();

    // item_name is a string column; an over-long insert would be truncated by
    // MySQL silently and rejected outright by strict Postgres.
    expect(strlen((string) $post->fresh()->item_name))->toBeLessThanOrEqual(255);
});

it('leaves a post with no description alone', function () {
    $post = FreightPost::factory()->create([
        'cargo_description' => null,
        'item_name' => null,
    ]);

    runTariffWeightMigration();

    expect($post->fresh()->item_name)->toBeNull();
});

it('is idempotent', function () {
    FreightPost::factory()->create([
        'cargo_description' => 'cement',
        'item_name' => null,
    ]);

    runTariffWeightMigration();
    $afterFirst = DB::table('freight_posts')->pluck('item_name', 'id');

    runTariffWeightMigration();

    expect(DB::table('freight_posts')->pluck('item_name', 'id'))->toEqual($afterFirst);
});

it('rolling back does not drop the columns', function () {
    // down() is intentionally empty. A migration whose up() is conditional
    // cannot have a symmetric down(): on an environment where it created
    // nothing, a real down() would drop columns it did not add.
    $migration = require database_path('migrations/2026_10_05_000001_add_tariff_weight_to_freight_posts_table.php');

    $migration->down();

    expect(Schema::hasColumn('freight_posts', 'tariff_weight'))->toBeTrue()
        ->and(Schema::hasColumn('freight_posts', 'tariff_weight_unit'))->toBeTrue();
});
