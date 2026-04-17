<?php

namespace App\Enums\PME;

enum DunningStrategy: string
{
    case Soft = 'soft';
    case Standard = 'standard';
    case Strict = 'strict';
    case None = 'none';

    /**
     * @return array<int, int>
     */
    public function offsets(): array
    {
        return match ($this) {
            self::Soft => [7, 15],
            self::Standard => [3, 7, 15, 30],
            self::Strict => [0, 3, 7, 15],
            self::None => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Soft => 'Douce',
            self::Standard => 'Standard',
            self::Strict => 'Ferme',
            self::None => 'Aucune relance',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Soft => 'WhatsApp à J+7 et J+15',
            self::Standard => 'WhatsApp à J+3, J+7, J+15 et J+30',
            self::Strict => 'WhatsApp à Jour J, J+3, J+7 et J+15',
            self::None => 'Fayeku n\'enverra aucun rappel',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Soft => 'Pour les bons payeurs, clients VIP',
            self::Standard => 'Recommandé par défaut',
            self::Strict => 'Pour les clients à risque',
            self::None => 'Paiements hors système',
        };
    }
}
