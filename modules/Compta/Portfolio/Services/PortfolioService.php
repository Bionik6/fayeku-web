<?php

namespace Modules\Compta\Portfolio\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Models\Invoice;

class PortfolioService
{
    /** @var array<string, Collection> */
    private array $smeIdsCache = [];

    public function activeSmeIds(Company $firm): Collection
    {
        if (! isset($this->smeIdsCache[$firm->id])) {
            $this->smeIdsCache[$firm->id] = AccountantCompany::where('accountant_firm_id', $firm->id)
                ->whereNull('ended_at')
                ->pluck('sme_company_id');
        }

        return $this->smeIdsCache[$firm->id];
    }

    public function invoicesForFirm(Company $firm): Builder
    {
        return Invoice::whereIn('company_id', $this->activeSmeIds($firm));
    }
}
