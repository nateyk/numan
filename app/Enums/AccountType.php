<?php

namespace App\Enums;

enum AccountType: string
{
    case Personal = 'personal';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Business => 'Business',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Personal => 'For individuals using NumanFreight personally.',
            self::Business => 'For companies and organizations.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
