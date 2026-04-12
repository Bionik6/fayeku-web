<?php

namespace App\Policies\PME;

use App\Enums\PME\QuoteStatus;
use App\Models\PME\Invoice;
use App\Models\PME\Quote;
use App\Models\Shared\User;

class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        return $user->companies()->where('companies.id', $quote->company_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->companies()->where('type', 'sme')->exists();
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->companies()->where('companies.id', $quote->company_id)->exists();
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote);
    }

    public function convertToInvoice(User $user, Quote $quote): bool
    {
        return $user->companies()->where('companies.id', $quote->company_id)->exists()
            && in_array($quote->status, [QuoteStatus::Sent, QuoteStatus::Accepted])
            && ! Invoice::query()->where('quote_id', $quote->id)->exists();
    }
}
