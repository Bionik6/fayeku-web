<?php

namespace App\Policies\PME;

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\PME\Invoice;
use App\Models\PME\ProposalDocument;
use App\Models\Shared\User;

class ProposalDocumentPolicy
{
    public function view(User $user, ProposalDocument $document): bool
    {
        return $this->ownsCompany($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->companies()->where('type', 'sme')->exists();
    }

    public function update(User $user, ProposalDocument $document): bool
    {
        return $this->ownsCompany($user, $document);
    }

    public function delete(User $user, ProposalDocument $document): bool
    {
        return $this->update($user, $document);
    }

    public function convertToInvoice(User $user, ProposalDocument $document): bool
    {
        if (! $this->ownsCompany($user, $document)) {
            return false;
        }

        if (Invoice::query()->where('proposal_document_id', $document->id)->exists()) {
            return false;
        }

        return match ($document->type) {
            ProposalDocumentType::Quote => in_array($document->status, [
                ProposalDocumentStatus::Sent,
                ProposalDocumentStatus::Accepted,
            ], true),
            ProposalDocumentType::Proforma => in_array($document->status, [
                ProposalDocumentStatus::Sent,
                ProposalDocumentStatus::PoReceived,
            ], true),
        };
    }

    public function markAsPoReceived(User $user, ProposalDocument $document): bool
    {
        return $document->type === ProposalDocumentType::Proforma
            && $this->ownsCompany($user, $document)
            && $document->status === ProposalDocumentStatus::Sent;
    }

    private function ownsCompany(User $user, ProposalDocument $document): bool
    {
        return $user->companies()->where('companies.id', $document->company_id)->exists();
    }
}
