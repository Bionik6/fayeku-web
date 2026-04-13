<?php

namespace App\Console\Commands;

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ReminderChannel;
use App\Enums\PME\ReminderMode;
use App\Exceptions\Shared\QuotaExceededException;
use App\Jobs\PME\SendReminderJob;
use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Models\PME\ReminderRule;
use App\Services\Shared\QuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAutoRemindersCommand extends Command
{
    protected $signature = 'reminders:process-auto';

    protected $description = 'Dispatch automatic reminders for overdue invoices based on company rules';

    public function __construct(
        private readonly QuotaService $quotaService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        Log::info('[AutoReminders] Démarrage du traitement des relances automatiques.');

        $companies = Company::query()
            ->where('reminder_settings->enabled', true)
            ->where('reminder_settings->mode', 'auto')
            ->with(['reminderRules' => fn ($q) => $q->where('is_active', true)])
            ->get();

        Log::info("[AutoReminders] {$companies->count()} company(s) en mode auto trouvée(s).");

        $dispatched = 0;

        foreach ($companies as $company) {
            if (! $this->isWithinSendWindow($company)) {
                Log::debug("[AutoReminders] {$company->name} — hors fenêtre d'envoi, ignorée.");

                continue;
            }

            foreach ($company->reminderRules as $rule) {
                $dispatched += $this->processRule($company, $rule);
            }
        }

        Log::info("[AutoReminders] Terminé — {$dispatched} relance(s) dispatché(es).");
        $this->info("Dispatched {$dispatched} automatic reminder(s).");

        return self::SUCCESS;
    }

    private function processRule(Company $company, ReminderRule $rule): int
    {
        $targetDate = now()->subDays($rule->trigger_days)->toDateString();

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [
                InvoiceStatus::Sent,
                InvoiceStatus::Overdue,
                InvoiceStatus::PartiallyPaid,
            ])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<=', $targetDate)
            ->whereDoesntHave('reminders', fn ($q) => $q
                ->where('channel', $rule->channel)
                ->where('mode', ReminderMode::Auto)
                ->whereDate('sent_at', '>=', $targetDate)
            )
            ->with('client')
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            if (! $this->clientHasContact($invoice, $rule->channel)) {
                continue;
            }

            try {
                $this->quotaService->authorize($company, 'reminders');
                $this->quotaService->consume($company, 'reminders');
            } catch (QuotaExceededException) {
                break;
            }

            SendReminderJob::dispatch($invoice, $company, $rule->channel, ReminderMode::Auto);
            $count++;
        }

        return $count;
    }

    private function isWithinSendWindow(Company $company): bool
    {
        $now = now();
        $start = (int) $company->getReminderSetting('send_hour_start', 8);
        $end = (int) $company->getReminderSetting('send_hour_end', 18);

        if ($company->getReminderSetting('exclude_weekends', true) && $now->isWeekend()) {
            return false;
        }

        return $now->hour >= $start && $now->hour < $end;
    }

    private function clientHasContact(Invoice $invoice, ReminderChannel $channel): bool
    {
        return match ($channel) {
            ReminderChannel::Email => filled($invoice->client?->email),
            default => filled($invoice->client?->phone),
        };
    }
}
