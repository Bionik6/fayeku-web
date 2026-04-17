<?php

namespace App\Console\Commands;

use App\Enums\PME\DunningStrategy;
use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Exceptions\Shared\QuotaExceededException;
use App\Jobs\PME\SendReminderJob;
use App\Models\Auth\Company;
use App\Models\PME\DunningTemplate;
use App\Models\PME\Invoice;
use App\Services\PME\DunningTemplateRenderer;
use App\Services\Shared\QuotaService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessAutoRemindersCommand extends Command
{
    protected $signature = 'reminders:process-auto';

    protected $description = 'Dispatch automatic reminders for overdue invoices based on each client\'s dunning strategy';

    private const SEND_HOUR_START = 8;

    private const SEND_HOUR_END = 18;

    public function __construct(
        private readonly QuotaService $quotaService,
        private readonly DunningTemplateRenderer $renderer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        Log::info('[AutoReminders] Démarrage du traitement des relances automatiques.');

        if (! $this->isWithinSendWindow()) {
            Log::info('[AutoReminders] Hors fenêtre d\'envoi (8h–18h hors week-ends), fin.');
            $this->info('Outside send window.');

            return self::SUCCESS;
        }

        $templates = DunningTemplate::query()
            ->where('active', true)
            ->get()
            ->keyBy('day_offset');

        if ($templates->isEmpty()) {
            Log::warning('[AutoReminders] Aucun template actif en base, rien à dispatcher.');

            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $dispatched = 0;

        Company::query()->chunk(50, function ($companies) use (&$dispatched, $templates, $today) {
            foreach ($companies as $company) {
                $dispatched += $this->processCompany($company, $templates, $today);
            }
        });

        Log::info("[AutoReminders] Terminé — {$dispatched} relance(s) dispatché(es).");
        $this->info("Dispatched {$dispatched} automatic reminder(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, DunningTemplate>  $templates
     */
    private function processCompany(Company $company, $templates, CarbonInterface $today): int
    {
        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->where('reminders_enabled', true)
            ->whereIn('status', [
                InvoiceStatus::Sent,
                InvoiceStatus::Overdue,
                InvoiceStatus::PartiallyPaid,
            ])
            ->whereNotNull('due_at')
            ->whereHas('client', fn ($q) => $q->where('dunning_strategy', '!=', DunningStrategy::None->value))
            ->with(['client', 'reminders' => fn ($q) => $q->where('mode', ReminderMode::Auto)])
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            /** @var DunningStrategy $strategy */
            $strategy = $invoice->client->dunning_strategy;
            $daysPastDue = $invoice->due_at->startOfDay()->diffInDays($today, false);

            if ($daysPastDue < 0) {
                continue;
            }

            $alreadySent = $invoice->reminders
                ->whereNotNull('day_offset')
                ->pluck('day_offset')
                ->all();

            foreach ($strategy->offsets() as $offset) {
                if ($offset > $daysPastDue) {
                    continue;
                }

                if (in_array($offset, $alreadySent, true)) {
                    continue;
                }

                $template = $templates->get($offset);

                if (! $template) {
                    Log::debug("[AutoReminders] Template manquant pour day_offset={$offset}, ignoré.");

                    continue;
                }

                $channel = $this->pickChannel($invoice);

                if ($channel === null) {
                    Log::debug("[AutoReminders] Facture {$invoice->reference} : client sans téléphone ni email, ignoré.");

                    continue;
                }

                try {
                    $this->quotaService->authorize($company, 'reminders');
                    $this->quotaService->consume($company, 'reminders');
                } catch (QuotaExceededException) {
                    Log::warning("[AutoReminders] Quota dépassé pour company {$company->id}, arrêt.");

                    return $count;
                }

                $body = $this->renderer->render($template, $invoice);

                SendReminderJob::dispatch($invoice, $company, $channel, ReminderMode::Auto, $body, $offset);
                $count++;
            }
        }

        return $count;
    }

    private function pickChannel(Invoice $invoice): ?ReminderChannel
    {
        if (filled($invoice->client?->phone)) {
            return ReminderChannel::WhatsApp;
        }

        if (filled($invoice->client?->email)) {
            return ReminderChannel::Email;
        }

        return null;
    }

    private function isWithinSendWindow(): bool
    {
        $now = now();

        if ($now->isWeekend()) {
            return false;
        }

        return $now->hour >= self::SEND_HOUR_START && $now->hour < self::SEND_HOUR_END;
    }
}
