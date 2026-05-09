<?php

namespace App\Enums\Auth;

enum OnboardingIntent: string
{
    case InvoiceFaster = 'INVOICE_FASTER';
    case RecoverUnpaid = 'RECOVER_UNPAID';
    case CashVisibility = 'CASH_VISIBILITY';
    case AccountantCollab = 'ACCOUNTANT_COLLAB';
    case All = 'ALL';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceFaster => 'Émettre des factures plus vite',
            self::RecoverUnpaid => 'Récupérer mes impayés (relances)',
            self::CashVisibility => 'Avoir une vision de ma trésorerie',
            self::AccountantCollab => 'Travailler avec mon comptable',
            self::All => 'Tout ça à la fois',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::InvoiceFaster => 'Création rapide, modèles prêts à l\'emploi.',
            self::RecoverUnpaid => 'Relances WhatsApp/SMS automatisées.',
            self::CashVisibility => 'Tableau de bord et exports comptables.',
            self::AccountantCollab => 'Accès partagé avec votre cabinet.',
            self::All => 'Tirez parti de tout Fayeku, étape par étape.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InvoiceFaster => 'document-text',
            self::RecoverUnpaid => 'bell-alert',
            self::CashVisibility => 'chart-bar',
            self::AccountantCollab => 'user-group',
            self::All => 'sparkles',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
