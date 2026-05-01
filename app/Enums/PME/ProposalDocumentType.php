<?php

namespace App\Enums\PME;

enum ProposalDocumentType: string
{
    case Quote = 'quote';
    case Proforma = 'proforma';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Devis',
            self::Proforma => 'Proforma',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Quote => 'DEV',
            self::Proforma => 'PRO',
        };
    }

    public function referencePrefix(): string
    {
        return 'FYK-'.$this->shortLabel().'-';
    }
}
