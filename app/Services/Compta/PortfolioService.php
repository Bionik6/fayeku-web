<?php

namespace App\Services\Compta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Enums\PME\InvoiceStatus;
use App\Models\PME\Invoice;

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

    /**
     * Determine the status of a client based on their invoices.
     *
     * Returns 'critical' if any overdue invoice exceeds 60 days past due,
     * 'watch' if any invoice is overdue or partially paid,
     * 'current' otherwise.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return 'critical'|'watch'|'current'
     */
    public function clientStatus(Collection $invoices): string
    {
        $hasCritical = $invoices->contains(
            fn (Invoice $inv) => $inv->status === InvoiceStatus::Overdue
                && $inv->due_at !== null
                && $inv->due_at->lt(now()->subDays(60))
        );

        if ($hasCritical) {
            return 'critical';
        }

        $hasUnpaid = $invoices->contains(
            fn (Invoice $inv) => in_array($inv->status, [InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid])
        );

        return $hasUnpaid ? 'watch' : 'current';
    }
}
