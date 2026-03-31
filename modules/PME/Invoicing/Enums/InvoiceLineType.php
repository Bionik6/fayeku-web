<?php

namespace Modules\PME\Invoicing\Enums;

enum InvoiceLineType: string
{
    case Acompte = 'acompte';
    case Heures = 'heures';
    case Jours = 'jours';
    case Produit = 'produit';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Acompte => 'Acompte',
            self::Heures => 'Heures',
            self::Jours => 'Jours',
            self::Produit => 'Produit',
            self::Service => 'Service',
        };
    }
}
