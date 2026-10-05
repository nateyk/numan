<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The password given to demo accounts.
 *
 * Read from DEMO_PASSWORD. If that is empty, a random one is generated and
 * printed once to the console, so a developer who forgot to set the env var can
 * still sign in without the answer being a constant everybody already knows.
 *
 * There used to be a shared default. It was published in the repository, it
 * created a superadmin, and "we will rotate it before launch" is not a control.
 * A random fallback removes the shared secret without breaking local dev.
 *
 * Memoised per process so every demo account gets the same password within one
 * seed run, which is the whole point of a shared demo password.
 */
final class DemoPassword
{
    /**
     * @var array{password: string, hashed: string, generated: bool}|null
     */
    private static ?array $resolved = null;

    /**
     * @return array{password: string, hashed: string, generated: bool}
     */
    public static function resolve(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $configured = trim((string) config('demo.password', env('DEMO_PASSWORD', '')));

        $generated = $configured === '';

        if ($generated) {
            $configured = Str::password(24);
        }

        self::$resolved = [
            'password' => $configured,
            'hashed' => Hash::make($configured),
            'generated' => $generated,
        ];

        if ($generated) {
            self::announce($configured);
        }

        return self::$resolved;
    }

    /**
     * The hash, for a seeder that only needs that.
     */
    public static function hashed(): string
    {
        return self::resolve()['hashed'];
    }

    /**
     * Forget the memoised value.
     *
     * Only for tests, which need to see a fresh resolution per case.
     */
    public static function flush(): void
    {
        self::$resolved = null;
    }

    /**
     * Tell the developer the generated password.
     *
     * Console only, and never in production. This deliberately does NOT go
     * through the logger: a password written to storage/logs/laravel.log is a
     * secret on disk that outlives the terminal it was printed to, and the whole
     * point of the generated password is that it exists in exactly one place.
     */
    private static function announce(string $password): void
    {
        if (app()->environment('production')) {
            return;
        }

        fwrite(STDOUT, PHP_EOL.sprintf(
            'DEMO_PASSWORD is not set, so a random password was generated for the demo accounts:%s    %s%s',
            PHP_EOL,
            $password,
            PHP_EOL
        ));
    }
}
