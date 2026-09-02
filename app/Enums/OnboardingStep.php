<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Capabilities = 'capabilities';
    case AccountType = 'account_type';
    case Details = 'details';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Capabilities => 'Your use',
            self::AccountType => 'Account',
            self::Details => 'Details',
            self::Completed => 'Completed',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Capabilities => self::AccountType,
            self::AccountType => self::Details,
            self::Details => self::Completed,
            self::Completed => null,
        };
    }

    public static function fromProfile(?string $value): self
    {
        return match ($value) {
            'account_type' => self::AccountType,
            'details' => self::Details,
            'completed' => self::Completed,
            default => self::Capabilities,
        };
    }
}
