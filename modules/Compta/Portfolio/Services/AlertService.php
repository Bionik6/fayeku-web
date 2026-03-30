<?php

namespace Modules\Compta\Portfolio\Services;

use Illuminate\Support\Collection;
use Modules\Auth\Models\Company;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Compta\Portfolio\Models\DismissedAlert;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

class AlertService
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $buildCache = [];

    public function __construct(private readonly PortfolioService $portfolio) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(Company $firm, ?string $filter = null, int $limit = 0): array
    {
        $cacheKey = $firm->id.':'.($filter ?? 'all');

        if (! isset($this->buildCache[$cacheKey])) {
            $this->buildCache[$cacheKey] = $this->doBuild($firm, $filter);
        }

        $alerts = $this->buildCache[$cacheKey];

        return $limit > 0 ? array_slice($alerts, 0, $limit) : $alerts;
    }

    public function count(Company $firm, ?User $user = null): int
    {
        $all = $this->build($firm);

        if (! $user) {
            return count($all);
        }

        $dismissed = DismissedAlert::where('user_id', $user->id)
            ->pluck('alert_key')
            ->toArray();

        return count(array_filter($all, fn (array $a) => ! in_array($a['alert_key'], $dismissed)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function doBuild(Company $firm, ?string $filter): array
    {
        $smeIds = $this->portfolio->activeSmeIds($firm);

        $alerts = [];

        if ($smeIds->isNotEmpty()) {
            if ($filter === null || $filter === 'critical') {
                $this->appendCritical($alerts, $smeIds);
            }

            if ($filter === null || $filter === 'watch') {
                $allInvoices = Invoice::query()
                    ->whereIn('company_id', $smeIds)
                    ->get()
                    ->groupBy('company_id');

                $this->appendWatch($alerts, $smeIds, $allInvoices);
            }
        }

        if ($filter === null || $filter === 'new') {
            $this->appendNew($alerts, $firm);
        }

        return $alerts;
    }

    /** @param array<int, array<string, mixed>> $alerts */
    private function appendCritical(array &$alerts, Collection $smeIds): void
    {
        $criticalInvoices = Invoice::query()
            ->whereIn('company_id', $smeIds)
            ->where('status', InvoiceStatus::Overdue->value)
            ->where('due_at', '<', now()->subDays(60))
            ->with('company')
            ->withCount('reminders')
            ->orderBy('due_at')
            ->get();

        foreach ($criticalInvoices->groupBy('company_id') as $companyId => $invoices) {
            $company = $invoices->first()->company;
            $count = $invoices->count();
            $totalAmount = $invoices->sum('total');
            $maxDaysLate = $invoices->max(fn ($inv) => (int) abs(now()->diffInDays($inv->due_at)));
            $totalReminders = $invoices->sum('reminders_count');

            if ($count === 1) {
                $invoice = $invoices->first();
                $reminderLabel = $totalReminders > 0
                    ? $totalReminders.' relance(s) envoyée(s)'
                    : 'Aucune relance envoyée';
                $subtitle = ($invoice->reference ?? 'FAC').' · '.number_format($totalAmount, 0, ',', ' ').' FCFA · J'.$maxDaysLate.' · '.$reminderLabel;
            } else {
                $reminderLabel = $totalReminders > 0
                    ? $totalReminders.' relance(s) envoyée(s)'
                    : 'Aucune relance envoyée';
                $subtitle = $count.' factures impayées · '.number_format($totalAmount, 0, ',', ' ').' FCFA · J'.$maxDaysLate.' max · '.$reminderLabel;
            }

            $alerts[] = [
                'type' => 'critical',
                'alert_key' => 'critical_'.$companyId,
                'invoice_id' => null,
                'company_id' => $companyId,
                'title' => $company->name.' · Impayé critique',
                'subtitle' => $subtitle,
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $alerts
     * @param  Collection<int, Collection>  $allInvoices
     */
    private function appendWatch(array &$alerts, Collection $smeIds, Collection $allInvoices): void
    {
        $cutoff = now()->subDays(30);

        $recentCompanyIds = $allInvoices
            ->filter(fn (Collection $invoices) => $invoices->contains(fn ($inv) => $inv->issued_at >= $cutoff))
            ->keys();

        $inactiveIds = $smeIds->diff($recentCompanyIds);

        if ($inactiveIds->isEmpty()) {
            return;
        }

        $inactiveCompanies = Company::query()->whereIn('id', $inactiveIds)->get();

        foreach ($inactiveCompanies as $company) {
            $invoices = $allInvoices->get($company->id, collect());
            $lastInvoice = $invoices->sortByDesc('issued_at')->first();
            $daysSince = $lastInvoice ? (int) abs(now()->diffInDays($lastInvoice->issued_at)) : null;

            $alerts[] = [
                'type' => 'watch',
                'alert_key' => 'watch_'.$company->id,
                'invoice_id' => null,
                'company_id' => $company->id,
                'title' => $company->name.' · Inactif depuis '.($daysSince ? $daysSince.' jours' : 'longtemps'),
                'subtitle' => 'Aucune facture émise ce mois'.($daysSince ? ' · Dernier contact il y a '.$daysSince.' jours' : ''),
            ];
        }
    }

    /** @param array<int, array<string, mixed>> $alerts */
    private function appendNew(array &$alerts, Company $firm): void
    {
        $newInvitations = PartnerInvitation::query()
            ->where('accountant_firm_id', $firm->id)
            ->where('status', 'accepted')
            ->where('accepted_at', '>=', now()->subDays(7))
            ->orderByDesc('accepted_at')
            ->get();

        $smeCompanyIds = $newInvitations
            ->pluck('sme_company_id')
            ->filter()
            ->unique();

        $smeCompanies = $smeCompanyIds->isNotEmpty()
            ? Company::query()->whereIn('id', $smeCompanyIds)->get()->keyBy('id')
            : collect();

        foreach ($newInvitations as $invitation) {
            $newSme = $invitation->sme_company_id
                ? $smeCompanies->get($invitation->sme_company_id)
                : null;

            $alerts[] = [
                'type' => 'new',
                'alert_key' => 'new_'.$invitation->id,
                'invoice_id' => null,
                'company_id' => $invitation->sme_company_id,
                'title' => ($newSme?->name ?? $invitation->invitee_name).' · Nouvelle inscription',
                'subtitle' => 'Via votre lien partenaire · Offre '.ucfirst($invitation->recommended_plan ?? 'Essentiel').' · Essai 2 mois',
            ];
        }
    }
}
