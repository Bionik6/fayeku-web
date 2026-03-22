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
    public function __construct(private readonly PortfolioService $portfolio) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(Company $firm, ?string $filter = null, int $limit = 0): array
    {
        $smeIds = $this->portfolio->activeSmeIds($firm);

        $alerts = [];

        if ($smeIds->isNotEmpty()) {
            $allInvoices = Invoice::query()
                ->whereIn('company_id', $smeIds)
                ->get()
                ->groupBy('company_id');

            if ($filter === null || $filter === 'critical') {
                $this->appendCritical($alerts, $smeIds);
            }

            if ($filter === null || $filter === 'watch') {
                $this->appendWatch($alerts, $smeIds, $allInvoices);
            }
        }

        if ($filter === null || $filter === 'new') {
            $this->appendNew($alerts, $firm);
        }

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

        foreach ($criticalInvoices as $invoice) {
            $daysLate = (int) now()->diffInDays($invoice->due_at);
            $reminderLabel = $invoice->reminders_count > 0
                ? $invoice->reminders_count.' relance(s) envoyée(s)'
                : 'Aucune relance envoyée';

            $alerts[] = [
                'type' => 'critical',
                'alert_key' => 'critical_'.$invoice->id,
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'title' => $invoice->company->name.' — impayé critique',
                'subtitle' => ($invoice->reference ?? 'FAC').' · '.number_format($invoice->total, 0, ',', ' ').' FCFA · J+'.$daysLate.' · '.$reminderLabel,
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $alerts
     * @param  Collection<int, Collection>  $allInvoices
     */
    private function appendWatch(array &$alerts, Collection $smeIds, Collection $allInvoices): void
    {
        $recentCompanyIds = Invoice::query()
            ->whereIn('company_id', $smeIds)
            ->where('issued_at', '>=', now()->subDays(30))
            ->pluck('company_id')
            ->unique();

        $inactiveIds = $smeIds->diff($recentCompanyIds);

        if ($inactiveIds->isEmpty()) {
            return;
        }

        $inactiveCompanies = Company::query()->whereIn('id', $inactiveIds)->get();

        foreach ($inactiveCompanies as $company) {
            $invoices = $allInvoices->get($company->id, collect());
            $lastInvoice = $invoices->sortByDesc('issued_at')->first();
            $daysSince = $lastInvoice ? (int) now()->diffInDays($lastInvoice->issued_at) : null;

            $alerts[] = [
                'type' => 'watch',
                'alert_key' => 'watch_'.$company->id,
                'invoice_id' => null,
                'company_id' => $company->id,
                'title' => $company->name.' — inactif depuis '.($daysSince ? $daysSince.' jours' : 'longtemps'),
                'subtitle' => 'Aucune facture émise ce mois'.($daysSince ? ' · Dernier contact il y a '.$daysSince.'j' : ''),
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

        foreach ($newInvitations as $invitation) {
            $newSme = $invitation->sme_company_id
                ? Company::query()->find($invitation->sme_company_id)
                : null;

            $alerts[] = [
                'type' => 'new',
                'alert_key' => 'new_'.$invitation->id,
                'invoice_id' => null,
                'company_id' => $invitation->sme_company_id,
                'title' => ($newSme?->name ?? $invitation->invitee_name)." — vient de s'inscrire",
                'subtitle' => 'Via votre lien partenaire · Plan '.ucfirst($invitation->recommended_plan ?? 'Essentiel').' · Trial 2 mois',
            ];
        }
    }
}
