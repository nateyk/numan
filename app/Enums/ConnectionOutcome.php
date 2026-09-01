<?php

namespace App\Enums;

enum ConnectionOutcome: string
{
    case Contacted = 'contacted';
    case Successful = 'successful';
    case NoResponse = 'no_response';
    case NotSuitable = 'not_suitable';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Contacted => 'Contacted',
            self::Successful => 'Successful',
            self::NoResponse => 'No Response',
            self::NotSuitable => 'Not Suitable',
            self::Cancelled => 'Cancelled',
            self::Unknown => 'Unknown',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
