<?php

namespace App\Enums\PME;

enum ProposalDocumentStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case PoReceived = 'po_received';
    case Converted = 'converted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public static function editable(): array
    {
        return [self::Draft, self::Sent];
    }

    public function isAllowedFor(ProposalDocumentType $type): bool
    {
        return match ($this) {
            self::PoReceived, self::Converted => $type === ProposalDocumentType::Proforma,
            default => true,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function archivable(): array
    {
        return [self::Declined, self::Expired, self::Cancelled];
    }
}
