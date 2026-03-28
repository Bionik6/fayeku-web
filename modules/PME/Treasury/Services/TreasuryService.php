<?php

namespace Modules\PME\Treasury\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Services\ClientService;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Services\InvoiceService;

class TreasuryService
{
    public function __construct(
        private InvoiceService $invoiceService,
        private ForecastService $forecastService,
        private ClientService $clientService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Company $company, string $period = '90d'): array
    {
        $period = in_array($period, ['30d', '90d'], true) ? $period : '90d';
        $horizonEnd = $this->horizonEnd($period);
        $clientProfiles = collect($this->clientService->portfolioRows($company, 'all'))
            ->keyBy('id')
            ->all();

        $receivables = $this->invoiceService->openReceivables($company);
        $allRows = $this->forecastService->rows($receivables, $clientProfiles);
        $rows = collect($allRows)
            ->filter(fn (array $row) => $row['estimated_date']->lessThanOrEqualTo($horizonEnd))
            ->values();

        $collectedAmount = (int) Invoice::query()
            ->where('company_id', $company->id)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->sum('amount_paid');

        $paidInvoices = Invoice::query()
            ->where('company_id', $company->id)
            ->where('status', InvoiceStatus::Paid)
            ->whereNotNull('paid_at')
            ->whereNotNull('issued_at')
            ->get();

        $averageCollectionDays = $paidInvoices->isNotEmpty()
            ? (int) round($paidInvoices->avg(
                fn (Invoice $invoice) => $invoice->issued_at->copy()->startOfDay()->diffInDays($invoice->paid_at->copy()->startOfDay(), true)
            ) ?? 0)
            : 0;

        $expectedInflows = (int) $rows->sum('estimated_amount');
        $atRiskAmount = (int) $rows
            ->filter(fn (array $row) => $row['confidence_score'] < 60)
            ->sum('remaining');

        return [
            'period' => $period,
            'period_label' => $period === '30d' ? '30 jours' : '90 jours',
            'subtitle' => sprintf(
                'Vision %s · %s → %s',
                $period === '30d' ? '30 jours' : '90 jours',
                now()->locale('fr_FR')->translatedFormat('F'),
                $horizonEnd->locale('fr_FR')->translatedFormat('F Y')
            ),
            'disclaimer' => 'Prévision des encaissements uniquement, hors sorties de trésorerie.',
            'kpis' => [
                'collected_amount' => $collectedAmount,
                'expected_inflows' => $expectedInflows,
                'average_collection_days' => $averageCollectionDays,
                'at_risk_amount' => $atRiskAmount,
            ],
            'forecast_cards' => $this->forecastCards($rows, $horizonEnd),
            'rows' => $rows->all(),
            'recommendations' => $this->recommendations($rows, $clientProfiles),
        ];
    }

    private function horizonEnd(string $period): CarbonInterface
    {
        return now()->startOfDay()->addDays($period === '30d' ? 30 : 90);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function forecastCards(Collection $rows, CarbonInterface $horizonEnd): array
    {
        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $nextStart = now()->copy()->addMonthNoOverflow()->startOfMonth();
        $nextEnd = now()->copy()->addMonthNoOverflow()->endOfMonth();
        $trendStart = now()->copy()->addMonthsNoOverflow(2)->startOfMonth();
        $total = max(1, (int) $rows->sum('estimated_amount'));

        $currentRows = $rows->filter(fn (array $row) => $row['estimated_date']->between($currentStart, $currentEnd));
        $nextRows = $rows->filter(fn (array $row) => $row['estimated_date']->between($nextStart, $nextEnd));
        $trendRows = $rows->filter(fn (array $row) => $row['estimated_date']->greaterThanOrEqualTo($trendStart));

        return [
            $this->cardPayload('Mois en cours', $this->monthLabel($currentStart), $currentRows, $total),
            $this->cardPayload('Mois suivant', $this->monthLabel($nextStart), $nextRows, $total),
            $this->cardPayload(
                'Tendance',
                $trendStart->greaterThan($horizonEnd)
                    ? 'Au-delà du mois suivant'
                    : $this->monthLabel($trendStart, false).' → '.$this->monthLabel($horizonEnd),
                $trendRows,
                $total
            ),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function cardPayload(string $title, string $caption, Collection $rows, int $total): array
    {
        $amount = (int) $rows->sum('estimated_amount');
        $count = $rows->count();
        $averageConfidence = $count > 0
            ? (int) round($rows->avg('confidence_score') ?? 0)
            : 0;

        return [
            'title' => $title,
            'caption' => $caption,
            'amount' => $amount,
            'count' => $count,
            'average_confidence' => $averageConfidence,
            'basis' => $count > 0
                ? sprintf('%s facture(s) · confiance moyenne %s%%', $count, $averageConfidence)
                : 'Aucune entrée estimée',
            'progress' => min(100, (int) round($amount / $total * 100)),
        ];
    }

    private function monthLabel(CarbonInterface $date, bool $includeYear = true): string
    {
        $label = $date->locale('fr_FR')->translatedFormat($includeYear ? 'F Y' : 'F');

        return str($label)->ucfirst()->toString();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $clientProfiles
     * @return array<int, array<string, string>>
     */
    private function recommendations(Collection $rows, array $clientProfiles): array
    {
        $cards = [];

        $riskRow = $rows
            ->filter(fn (array $row) => $row['confidence_score'] < 60)
            ->sortByDesc('remaining')
            ->first();

        if ($riskRow) {
            $cards[] = [
                'type' => 'risk',
                'title' => 'Alerte encaissement',
                'body' => sprintf(
                    'Si %s reste impayée, %s pèse à elle seule %s FCFA à risque.',
                    $riskRow['document'],
                    $riskRow['client_name'],
                    number_format($riskRow['remaining'], 0, ',', ' ')
                ),
                'tone' => 'rose',
            ];
        }

        $improvementRow = $rows
            ->filter(fn (array $row) => $row['days_overdue'] > 7 && $row['reminders_count'] === 0)
            ->sortByDesc('remaining')
            ->first();

        if ($improvementRow) {
            $cards[] = [
                'type' => 'improvement',
                'title' => 'Amélioration possible',
                'body' => sprintf(
                    'Vos encaissements pourraient s’améliorer avec une relance plus tôt sur %s.',
                    $improvementRow['document']
                ),
                'tone' => 'amber',
            ];
        }

        $positiveClient = collect($clientProfiles)
            ->filter(fn (array $profile) => (int) ($profile['total_collected'] ?? 0) > 0)
            ->sortBy([
                ['payment_score', 'desc'],
                ['average_payment_days', 'asc'],
                ['total_collected', 'desc'],
            ])
            ->first();

        if ($positiveClient) {
            $cards[] = [
                'type' => 'positive',
                'title' => 'Bon signal',
                'body' => (int) ($positiveClient['average_payment_days'] ?? 0) > 0
                    ? sprintf(
                        '%s règle en moyenne en %sj, bon signal pour vos prochaines échéances.',
                        $positiveClient['name'],
                        $positiveClient['average_payment_days']
                    )
                    : sprintf('%s paie rapidement, bon signal.', $positiveClient['name']),
                'tone' => 'teal',
            ];
        }

        return array_slice($cards, 0, 3);
    }
}
