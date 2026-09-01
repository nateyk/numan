<?php

namespace App\Enums;

enum FreightInterestStatus: string
{
    case Submitted = 'submitted';
    case Shortlisted = 'shortlisted';
    case Selected = 'selected';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Shortlisted => 'Shortlisted',
            self::Selected => 'Selected',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Submitted => in_array($target, [self::Shortlisted, self::Rejected, self::Withdrawn], true),
            self::Shortlisted => in_array($target, [self::Selected, self::Rejected, self::Withdrawn], true),
            self::Selected => in_array($target, [], true),
            self::Rejected => in_array($target, [], true),
            self::Withdrawn => in_array($target, [], true),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
