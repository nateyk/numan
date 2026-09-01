<?php

namespace App\Enums;

enum ScheduleType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-Time',
            self::Recurring => 'Recurring',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
