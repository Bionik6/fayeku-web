<?php

namespace App\Enums\Compta;

enum PartnerTier: string
{
    case Partner = 'partner';
    case Gold = 'gold';
    case Platinum = 'platinum';

    public static function fromActiveClients(int $count): self
    {
        return match (true) {
            $count >= 15 => self::Platinum,
            $count >= 5 => self::Gold,
            default => self::Partner,
        };
    }
}
