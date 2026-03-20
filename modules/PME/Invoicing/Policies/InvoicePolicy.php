<?php

namespace Modules\PME\Invoicing\Policies;

use Modules\Auth\Models\AccountantCompany;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->companies()->where('companies.id', $invoice->company_id)->exists()) {
            return true;
        }
        $firmIds = $user->companies()->where('type', 'accountant_firm')->pluck('companies.id');
        return AccountantCompany::whereIn('accountant_firm_id', $firmIds)
            ->where('sme_company_id', $invoice->company_id)
            ->whereNull('ended_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->companies()->where('type', 'sme')->exists();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->companies()->where('companies.id', $invoice->company_id)->exists();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
}
