<?php

namespace Modules\Compta\Portfolio\Services;

use Illuminate\Support\Collection;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Models\Invoice;

class PortfolioService
{
    public function activeSmeIds(Company $firm): Collection
    {
        return AccountantCompany::where('accountant_firm_id', $firm->id)
            ->whereNull('ended_at')
            ->pluck('sme_company_id');
    }

    public function invoicesForFirm(Company $firm): \Illuminate\Database\Eloquent\Builder
    {
        return Invoice::whereIn('company_id', $this->activeSmeIds($firm));
    }
}
