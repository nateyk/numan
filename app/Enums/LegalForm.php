<?php

namespace App\Enums;

enum LegalForm: string
{
    case PrivateLimitedCompany = 'Private Limited Company (PLC)';
    case ShareCompany = 'Share Company (SC)';
    case SoleProprietorship = 'Sole Proprietorship';
    case Partnership = 'Partnership';
    case GovernmentOrganization = 'Government Organization';
    case NGO = 'NGO';
    case Association = 'Association';
    case Cooperative = 'Cooperative';
    case Other = 'Other';

    public function label(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
