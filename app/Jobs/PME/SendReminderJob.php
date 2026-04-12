<?php

namespace App\Jobs\PME;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Auth\Company;
use App\Enums\PME\ReminderChannel;
use App\Services\PME\ReminderService;
use App\Models\PME\Invoice;

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
