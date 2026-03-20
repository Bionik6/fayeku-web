<?php

namespace Modules\PME\Collection\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\Models\Company;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Services\ReminderService;
use Modules\PME\Invoicing\Models\Invoice;

class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Company $company,
        public readonly ReminderChannel $channel,
    ) {}

    public function handle(ReminderService $service): void
    {
        $service->send($this->invoice, $this->company, $this->channel);
    }
}
