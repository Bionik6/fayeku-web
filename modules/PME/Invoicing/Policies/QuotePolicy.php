<?php

namespace Modules\PME\Invoicing\Policies;

use Modules\PME\Invoicing\Models\Quote;
use Modules\Shared\Models\User;

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
}
