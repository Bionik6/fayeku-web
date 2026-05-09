<?php

namespace App\Enums\Auth;

enum LegalForm: string
{
    case Sarl = 'SARL';
    case Sa = 'SA';
    case Sas = 'SAS';
    case Suarl = 'SUARL';
    case Gie = 'GIE';
    case AutoEntrepreneur = 'AUTO_ENTREPRENEUR';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Sarl => 'SARL — Société à Responsabilité Limitée',
            self::Sa => 'SA — Société Anonyme',
            self::Sas => 'SAS — Société par Actions Simplifiée',
            self::Suarl => 'SUARL — SARL Unipersonnelle',
            self::Gie => 'GIE — Groupement d\'Intérêt Économique',
            self::AutoEntrepreneur => 'Auto-entrepreneur',
            self::Other => 'Autre',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
