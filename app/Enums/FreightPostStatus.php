<?php

namespace App\Enums;

enum FreightPostStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Active = 'active';
    case Closed = 'closed';
    case Expired = 'expired';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Expired => 'Expired',
            self::Rejected => 'Rejected',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Submitted], true),
            self::Submitted => in_array($target, [self::UnderReview, self::Active, self::Rejected], true),
            self::UnderReview => in_array($target, [self::Active, self::Rejected], true),
            self::Active => in_array($target, [self::Closed, self::Expired], true),
            default => false,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
