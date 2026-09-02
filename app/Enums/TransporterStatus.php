<?php

namespace App\Enums;

enum TransporterStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Pending], true),
            self::Pending => in_array($target, [self::Approved, self::Rejected], true),
            self::Approved => in_array($target, [self::Rejected], true),
            self::Rejected => in_array($target, [self::Pending], true),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
