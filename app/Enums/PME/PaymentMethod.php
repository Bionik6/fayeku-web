<?php

namespace App\Enums\PME;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case MobileMoney = 'mobile_money';
    case Check = 'check';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::Transfer => 'Virement',
            self::MobileMoney => 'Mobile Money',
            self::Check => 'Chèque',
            self::Card => 'Carte bancaire',
            self::Other => 'Autre',
        };
    }
}
