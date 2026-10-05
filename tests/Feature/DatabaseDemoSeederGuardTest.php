<?php

use App\Models\User;
use Database\Seeders\CargoTypeSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\TruckTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;

/**
 * The demo seeders must refuse to run in production.
 *
 * Both create accounts whose password is published in the repository, and
 * DemoDataSeeder creates a superadmin among them. db:seed with no --class runs
 * every seeder, so the guard has to live in the seeder itself rather than in the
 * deploy script or a runbook instruction nobody reads under pressure.
 *
 * The guard aborts rather than warns. A warning on a command that creates a
 * superadmin with a known password is not a control.
 */
uses(RefreshDatabase::class);

/**
 * Put the application into an environment for the duration of a callback.
 */
function inEnvironment(string $environment, Closure $callback): mixed
{
    $original = app()->environment();

    app()['env'] = $environment;

    try {
        return $callback();
    } finally {
        app()['env'] = $original;
    }
}

it('refuses to seed demo data in production', function (string $seeder) {
    expect(fn () => inEnvironment('production', fn () => (new $seeder)->run()))
        ->toThrow(RuntimeException::class);
})->with([
    DatabaseSeeder::class,
    DemoDataSeeder::class,
]);

it('says why, and says what to do instead', function (string $seeder) {
    expect(fn () => inEnvironment('production', fn () => (new $seeder)->run()))
        ->toThrow(RuntimeException::class, 'Refusing to run');
})->with([
    DatabaseSeeder::class,
    DemoDataSeeder::class,
]);

it('creates no user at all when it refuses', function () {
    expect(User::count())->toBe(0);

    try {
        inEnvironment('production', fn () => (new DemoDataSeeder)->run());
    } catch (RuntimeException) {
        // expected
    }

    // The point of aborting rather than warning: nothing is half-written.
    expect(User::count())->toBe(0);
});

/**
 * Invoke the private guard on its own.
 *
 * The seeder bodies call $this->command->info(), which is null unless artisan is
 * driving, so running them wholesale here would be testing the wrong thing.
 */
function callGuard(object $seeder, string $environment): mixed
{
    return inEnvironment($environment, function () use ($seeder) {
        $method = new ReflectionMethod($seeder, 'guardAgainstProduction');
        $method->setAccessible(true);

        return $method->invoke($seeder, $seeder::class);
    });
}

it('allows the seeder to proceed outside production', function (string $seeder) {
    // The guard must not break local development or CI, where these seeders are
    // the entire point.
    // Called directly rather than through ->not->toThrow(), which needs an
    // exception class to negate and so cannot express "throws nothing".
    expect(callGuard(new $seeder, 'local'))->toBeNull();
})->with([
    DatabaseSeeder::class,
    DemoDataSeeder::class,
]);

it('does not treat staging, local or testing as production', function (string $environment) {
    // The guard keys on production exactly, so anything else must be allowed
    // through. A guard that also caught staging would block every rehearsal of
    // the deploy that matters most.
    expect(inEnvironment($environment, fn (): bool => app()->environment('production')))
        ->toBeFalse();
})->with(['staging', 'local', 'testing', 'development']);

it('leaves the reference-data seeders unguarded', function () {
    // These are wanted in production, and the runbook tells the operator to run
    // them individually once DatabaseSeeder refuses.
    foreach ([LocationSeeder::class, CargoTypeSeeder::class, TruckTypeSeeder::class] as $reference) {
        expect(inEnvironment('production', function () use ($reference) {
            $source = file_get_contents((new ReflectionClass($reference))->getFileName());

            return str_contains($source, 'guardAgainstProduction');
        }))->toBeFalse();
    }
});
