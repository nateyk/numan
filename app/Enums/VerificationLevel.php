<?php

namespace App\Enums;

enum VerificationLevel: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::Verified => 'Verified',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Unverified => 'Profile not yet reviewed',
            self::Verified => 'Profile reviewed — documents and business info verified. Not a guarantee or insurance.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
