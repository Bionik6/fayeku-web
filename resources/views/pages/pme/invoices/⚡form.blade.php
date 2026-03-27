<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Mail\InvoiceMail;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Services\CurrencyService;
use Modules\PME\Invoicing\Services\InvoiceService;

new #[Title('Facture')] #[Layout('layouts::pme')] class extends Component {
    public ?Invoice $invoice = null;

    public bool $isEditing = false;

    public ?Company $company = null;

    public string $reference = '';

    public string $clientId = '';

    public string $clientSearch = '';

    public string $issuedAt = '';

    public string $dueAt = '';

    public string $currency = 'XOF';

    public int $taxRate = 18;

    public ?int $discount = 0;

    public int $customTaxRate = 0;

    public string $taxMode = '18';

    public string $notes = '';

    public string $paymentMethod = '';

    public string $paymentDetails = '';

    /** @var array<int, string> */
    public array $reminderSchedule = ['-7', '-2', '0', '+7'];

    public string $dueDatePreset = '30';

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public bool $showCancelModal = false;

    public bool $showClientModal = false;

    public bool $showSendModal = false;

    public string $clientName = '';

    public string $clientSector = '';

    public string $clientPhone = '';

    public string $clientPhoneCountry = 'SN';

    public string $clientEmail = '';

    public string $clientTaxId = '';

    public string $clientAddress = '';

    public string $sendChannel = 'email';

    public string $sendRecipient = '';

    public string $sendMessage = '';

    public ?string $lastSavedAt = null;

    /** @var array{decimals: int, decSep: string, thousandsSep: string, label: string} */
    public array $currencyJs = [];

    public function updatedCurrency(): void
    {
        $this->currencyJs = CurrencyService::jsConfig($this->currency);
    }

    public function mount(?Invoice $invoice = null): void
    {
        $this->company = auth()->user()->companies()->where('type', 'sme')->first();

        abort_unless($this->company, 403);

        if ($invoice && $invoice->exists) {
            abort_unless(auth()->user()->can('update', $invoice), 403);
            abort_unless($invoice->company_id === $this->company->id, 403);

            $service = app(InvoiceService::class);

            if (! $service->canEdit($invoice)) {
                session()->flash('error', __('Cette facture ne peut plus être modifiée.'));
                $this->redirect(route('pme.invoices.index'), navigate: true);

                return;
            }

            $this->invoice = $invoice->load('lines', 'client');
            $this->isEditing = true;
            $this->reference = $invoice->reference ?? '';
            $this->clientId = $invoice->client_id ?? '';
            $this->issuedAt = $invoice->issued_at?->format('Y-m-d') ?? '';
            $this->dueAt = $invoice->due_at?->format('Y-m-d') ?? '';
            $this->currency = $invoice->currency ?? 'XOF';
            $this->discount = $invoice->discount ?? 0;
            $this->notes = $invoice->notes ?? '';
            $this->paymentMethod = $invoice->payment_method ?? '';
            $this->paymentDetails = $invoice->payment_details ?? '';
            $this->reminderSchedule = $invoice->reminder_schedule ?? ['-7', '-2', '0', '+7'];
            $this->dueDatePreset = 'custom';

            $firstLine = $invoice->lines->first();
            $rate = $firstLine?->tax_rate ?? 18;
            $this->taxRate = $rate;

            if ($rate === 0) {
                $this->taxMode = '0';
            } elseif ($rate === 18) {
                $this->taxMode = '18';
            } else {
                $this->taxMode = 'custom';
                $this->customTaxRate = $rate;
            }

            $this->lines = $invoice->lines->map(fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
            ])->toArray();
        } else {
            abort_unless(auth()->user()->can('create', Invoice::class), 403);

            $this->reference = app(InvoiceService::class)->generateReference($this->company);
            $this->issuedAt = now()->format('Y-m-d');
            $this->dueAt = now()->addDays(30)->format('Y-m-d');
            $this->lines = [$this->emptyLine()];
        }

        $this->currencyJs = CurrencyService::jsConfig($this->currency);
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function clients(): array
    {
        if (! $this->company) {
            return [];
        }

        $query = Client::query()->where('company_id', $this->company->id);

        if ($this->clientSearch !== '') {
            $term = '%'.mb_strtolower(trim($this->clientSearch)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
            });
        }

        return $query->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone', 'sector'])
            ->toArray();
    }

    #[Computed]
    public function selectedClient(): ?Client
    {
        if ($this->clientId === '') {
            return null;
        }

        return Client::query()
            ->where('id', $this->clientId)
            ->where('company_id', $this->company?->id)
            ->first();
    }

    /** @return array{average_days: int, outstanding: int, last_invoice_date: string|null} */
    #[Computed]
    public function clientContext(): array
    {
        $client = $this->selectedClient;

        if (! $client) {
            return ['average_days' => 0, 'outstanding' => 0, 'last_invoice_date' => null];
        }

        $invoices = $client->invoices()->get(['status', 'total', 'amount_paid', 'issued_at', 'paid_at']);

        $paidInvoices = $invoices->filter(fn ($inv) => $inv->status === InvoiceStatus::Paid && $inv->paid_at && $inv->issued_at);
        $averageDays = 0;

        if ($paidInvoices->isNotEmpty()) {
            $averageDays = (int) round($paidInvoices->avg(fn ($inv) => $inv->issued_at->diffInDays($inv->paid_at)));
        }

        $outstanding = $invoices
            ->filter(fn ($inv) => in_array($inv->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid]))
            ->sum(fn ($inv) => $inv->total - $inv->amount_paid);

        $lastInvoice = $invoices->sortByDesc('issued_at')->first();

        return [
            'average_days' => $averageDays,
            'outstanding' => $outstanding,
            'last_invoice_date' => $lastInvoice?->issued_at?->locale('fr_FR')->translatedFormat('d M Y'),
        ];
    }

    #[Computed]
    public function currencyLabel(): string
    {
        return CurrencyService::label($this->currency);
    }

    #[Computed]
    public function currencyConfig(): array
    {
        return CurrencyService::jsConfig($this->currency);
    }

    /** @return array{subtotal: int, tax_amount: int, total: int} */
    #[Computed]
    public function computedTotals(): array
    {
        return app(InvoiceService::class)->calculateInvoiceTotals($this->lines, $this->taxRate, $this->discount ?? 0);
    }

    #[Computed]
    public function formattedDueDate(): string
    {
        if ($this->dueAt === '') {
            return '';
        }

        try {
            return Carbon::parse($this->dueAt)->locale('fr_FR')->translatedFormat('d F Y');
        } catch (\Exception) {
            return '';
        }
    }

    #[Computed]
    public function hasFormData(): bool
    {
        if ($this->clientId !== '') {
            return true;
        }

        foreach ($this->lines as $line) {
            if (($line['description'] ?? '') !== '' || ($line['unit_price'] ?? 0) > 0) {
                return true;
            }
        }

        if ($this->notes !== '') {
            return true;
        }

        return ($this->discount ?? 0) > 0;
    }

    public function confirmCancel(): void
    {
        if ($this->hasFormData) {
            $this->showCancelModal = true;

            return;
        }

        $this->redirect(route('pme.invoices.index'), navigate: true);
    }

    public function cancel(): void
    {
        $this->redirect(route('pme.invoices.index'), navigate: true);
    }

    public function updatedTaxMode(string $value): void
    {
        $this->taxRate = match ($value) {
            '0' => 0,
            '18' => 18,
            'custom' => $this->customTaxRate,
            default => 18,
        };
    }

    public function updatedCustomTaxRate(?int $value): void
    {
        if ($this->taxMode === 'custom') {
            $this->taxRate = max(0, min(100, $value ?? 0));
        }
    }

    public function updatedDueDatePreset(string $value): void
    {
        if ($value === 'custom' || $this->issuedAt === '') {
            return;
        }

        $base = Carbon::parse($this->issuedAt);

        $this->dueAt = match ($value) {
            '0' => $base->format('Y-m-d'),
            '7' => $base->addDays(7)->format('Y-m-d'),
            '15' => $base->addDays(15)->format('Y-m-d'),
            '30' => $base->addDays(30)->format('Y-m-d'),
            default => $this->dueAt,
        };
    }

    public function updatedIssuedAt(): void
    {
        if ($this->dueDatePreset !== 'custom') {
            $this->updatedDueDatePreset($this->dueDatePreset);
        }
    }

    public function selectClient(string $id): void
    {
        $this->clientId = $id;
        $this->clientSearch = '';
        $this->resetErrorBag('clientId');

        $client = $this->selectedClient;

        if ($client) {
            $this->sendRecipient = $client->email ?? $client->phone ?? '';
        }
    }

    public function clearClient(): void
    {
        $this->clientId = '';
        $this->clientSearch = '';
        $this->sendRecipient = '';
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function toggleReminder(string $value): void
    {
        if (in_array($value, $this->reminderSchedule)) {
            $this->reminderSchedule = array_values(array_filter(
                $this->reminderSchedule,
                fn (string $v) => $v !== $value,
            ));
        } else {
            $this->reminderSchedule[] = $value;
        }
    }

    /**
     * Clear line-level validation errors when the user types.
     */
    public function updatedLines(): void
    {
        $this->resetErrorBag('lines');

        foreach (array_keys($this->lines) as $i) {
            $this->resetErrorBag("lines.{$i}.description");
            $this->resetErrorBag("lines.{$i}.quantity");
            $this->resetErrorBag("lines.{$i}.unit_price");
        }
    }

    public function saveDraft(): void
    {
        $this->validateForm();

        $service = app(InvoiceService::class);

        $data = $this->buildData();
        $lines = $this->buildLines();

        if ($this->isEditing) {
            $service->update($this->invoice, $data, $lines);
            $this->invoice->refresh();
        } else {
            $this->invoice = $service->create($this->company, $data, $lines);
            $this->isEditing = true;
        }

        $this->lastSavedAt = now()->format('H:i');

        session()->flash('success', __('Brouillon enregistré.'));
    }

    public function openSendModal(): void
    {
        $totals = $this->computedTotals;

        if ($totals['total'] <= 0) {
            $this->addError('lines', __('Le montant de la facture doit être supérieur à 0.'));

            return;
        }

        $client = $this->selectedClient;

        if ($client) {
            $this->sendRecipient = $client->email ?? $client->phone ?? '';
        }

        $formattedTotal = CurrencyService::format($totals['total'], $this->currency);
        $this->sendMessage = __("Bonjour,\n\nVeuillez trouver ci-joint votre facture :reference d'un montant de :total.\n\nCordialement.", [
            'reference' => $this->reference,
            'total' => $formattedTotal,
        ]);

        $this->showSendModal = true;
    }

    public function previewPdf(): void
    {
        $this->saveDraft();

        if ($this->invoice) {
            $this->dispatch('open-pdf', url: route('pme.invoices.pdf', $this->invoice));
        }
    }

    public function send(): void
    {
        $this->saveDraft();

        if ($this->sendChannel === 'pdf') {
            $this->showSendModal = false;
            $this->dispatch('open-pdf', url: route('pme.invoices.pdf', $this->invoice));

            return;
        }

        if ($this->sendChannel === 'email') {
            $this->validate([
                'sendRecipient' => ['required', 'email'],
            ], [
                'sendRecipient.required' => __('L\'adresse email du destinataire est requise.'),
                'sendRecipient.email' => __('L\'adresse email doit être valide.'),
            ]);

            $this->invoice->loadMissing(['company', 'client', 'lines']);

            Mail::to($this->sendRecipient)->send(
                new InvoiceMail($this->invoice, $this->sendMessage)
            );
        }

        $service = app(InvoiceService::class);
        $service->markAsSent($this->invoice);

        session()->flash('success', __('Facture envoyée avec succès.'));
        $this->redirect(route('pme.invoices.index'), navigate: true);
    }

    public function openClientModal(): void
    {
        $this->resetValidation();
        $this->resetClientForm();
        $this->showClientModal = true;
    }

    public function saveClient(): void
    {
        abort_unless($this->company && auth()->user()->can('create', Client::class), 403);

        $validated = $this->validate([
            'clientName' => ['required', 'string', 'max:255'],
            'clientSector' => ['nullable', 'string', 'max:100'],
            'clientPhone' => ['nullable', 'string', 'max:30'],
            'clientEmail' => ['nullable', 'email', 'max:255'],
            'clientTaxId' => ['nullable', 'string', 'max:100'],
            'clientAddress' => ['nullable', 'string', 'max:500'],
        ], [
            'clientName.required' => __('Le nom du client est requis.'),
            'clientEmail.email' => __("L'adresse email doit être valide."),
        ]);

        $client = Client::query()->create([
            'company_id' => $this->company->id,
            'name' => trim($validated['clientName']),
            'sector' => $this->emptyToNull($validated['clientSector'] ?? ''),
            'phone' => $this->normalizePhone($validated['clientPhone'] ?? ''),
            'email' => $this->emptyToNull($validated['clientEmail'] ?? ''),
            'tax_id' => $this->emptyToNull($validated['clientTaxId'] ?? ''),
            'address' => $this->emptyToNull($validated['clientAddress'] ?? ''),
        ]);

        $this->showClientModal = false;
        $this->selectClient($client->id);
    }

    private function validateForm(): void
    {
        $this->validate([
            'clientId' => ['required', 'string', 'exists:clients,id'],
            'reference' => ['required', 'string', 'max:50'],
            'issuedAt' => ['required', 'date'],
            'dueAt' => ['required', 'date', 'after_or_equal:issuedAt'],
            'currency' => ['required', 'string', 'in:'.implode(',', CurrencyService::codes())],
            'taxRate' => ['required', 'integer', 'min:0', 'max:100'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paymentMethod' => ['nullable', 'string', 'in:wave,orange_money,cash,bank_transfer'],
            'paymentDetails' => ['nullable', 'string', 'max:1000'],
            'reminderSchedule' => ['nullable', 'array'],
            'reminderSchedule.*' => ['string', 'in:-7,-2,0,+7,+15,+30'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:'.CurrencyService::maxAmount($this->currency)],
        ], [
            'clientId.required' => __('Veuillez sélectionner un client.'),
            'reference.required' => __('La référence est requise.'),
            'issuedAt.required' => __("La date d'émission est requise."),
            'dueAt.required' => __("La date d'échéance est requise."),
            'dueAt.after_or_equal' => __("La date d'échéance ne peut pas être antérieure à la date d'émission."),
            'lines.required' => __('Ajoutez au moins une ligne à votre facture.'),
            'lines.min' => __('Ajoutez au moins une ligne à votre facture.'),
            'lines.*.description.required' => __('La désignation est requise.'),
            'lines.*.quantity.required' => __('La quantité est requise.'),
            'lines.*.quantity.min' => __('La quantité doit être au moins 1.'),
            'lines.*.unit_price.required' => __('Le prix unitaire est requis.'),
            'lines.*.unit_price.max' => __('Le prix unitaire ne peut pas dépasser 999 999 999.'),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildData(): array
    {
        return [
            'client_id' => $this->clientId,
            'reference' => $this->reference,
            'currency' => $this->currency,
            'issued_at' => $this->issuedAt,
            'due_at' => $this->dueAt,
            'tax_rate' => $this->taxRate,
            'discount' => $this->discount ?? 0,
            'notes' => $this->emptyToNull($this->notes),
            'payment_method' => $this->emptyToNull($this->paymentMethod),
            'payment_details' => $this->emptyToNull($this->paymentDetails),
            'reminder_schedule' => $this->reminderSchedule,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildLines(): array
    {
        return collect($this->lines)->map(fn (array $line) => [
            'description' => $line['description'],
            'quantity' => (int) $line['quantity'],
            'unit_price' => (int) $line['unit_price'],
        ])->toArray();
    }

    /** @return array<string, mixed> */
    private function emptyLine(): array
    {
        return [
            'description' => '',
            'quantity' => 1,
            'unit_price' => 0,
        ];
    }

    private function emptyToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return '+'.$digits;
        }

        $prefix = match ($this->clientPhoneCountry) {
            'CI' => '225',
            default => '221',
        };

        if (str_starts_with($digits, $prefix)) {
            return '+'.$digits;
        }

        return '+'.$prefix.$digits;
    }

    private function resetClientForm(): void
    {
        $this->clientName = '';
        $this->clientSector = '';
        $this->clientPhone = '';
        $this->clientPhoneCountry = $this->company?->country_code ?? 'SN';
        $this->clientEmail = '';
        $this->clientTaxId = '';
        $this->clientAddress = '';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 pb-24 lg:pb-6" x-on:open-pdf.window="window.open($event.detail.url, '_blank')">
    {{-- Flash messages --}}
    @if (session('success'))
        <section class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </section>
    @endif

    @if (session('error'))
        <section class="rounded-[1.75rem] border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
            {{ session('error') }}
        </section>
    @endif

    {{-- Header --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-ink">
                        {{ $isEditing ? __('Modifier la facture') : __('Nouvelle facture') }}
                    </h2>
                    @if ($isEditing && $invoice)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $reference }}</span>
                    @endif
                </div>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-700">
                    @if ($isEditing && $invoice?->status === InvoiceStatus::Sent)
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-blue-400"></span>{{ __('Envoyée') }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-400"></span>{{ __('Brouillon') }}</span>
                    @endif
                    @if ($lastSavedAt)
                        <span class="text-xs text-slate-600">{{ __('Sauvegardé à :time', ['time' => $lastSavedAt]) }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" wire:click="confirmCancel" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">{{ __('Annuler') }}</button>
                <button type="button" wire:click="previewPdf" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">
                    <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    {{ __('Aperçu PDF') }}
                </button>
                <button type="button" wire:click="saveDraft" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">
                    <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    {{ __('Enregistrer brouillon') }}
                </button>
                <button type="button" wire:click="openSendModal" class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">
                    <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                    {{ __('Envoyer') }}
                </button>
            </div>
        </div>
        @if ($isEditing && $invoice?->status === InvoiceStatus::Sent)
            <div class="border-t border-amber-100 bg-amber-50 px-6 py-3 text-sm text-amber-800">
                <svg class="mr-1.5 inline size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                {{ __('Cette facture a déjà été envoyée. Toute modification impactera le document.') }}
            </div>
        @endif
    </section>

    {{-- Main content: 2-column grid --}}
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- LEFT COLUMN --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Client block --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Client') }}</h3>
                @if ($clientId && $this->selectedClient)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-ink">{{ $this->selectedClient->name }}</p>
                                <div class="mt-1 flex flex-wrap gap-3 text-sm text-slate-700">
                                    @if ($this->selectedClient->email) <span>{{ $this->selectedClient->email }}</span> @endif
                                    @if ($this->selectedClient->phone) <span>{{ $this->selectedClient->phone }}</span> @endif
                                </div>
                            </div>
                            <div class="ml-4 flex shrink-0 items-center gap-2">
                                <button type="button" wire:click="clearClient" class="text-xs font-medium text-primary transition hover:text-primary-strong">{{ __('Changer') }}</button>
                                <span class="text-slate-300">|</span>
                                <button type="button" wire:click="clearClient" class="text-xs font-medium text-slate-600 transition hover:text-rose-600">{{ __('Retirer') }}</button>
                            </div>
                        </div>
                        @php $ctx = $this->clientContext; @endphp
                        @if ($ctx['average_days'] > 0 || $ctx['outstanding'] > 0 || $ctx['last_invoice_date'])
                            <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-700">
                                @if ($ctx['average_days'] > 0) <span>{{ __('Délai moyen :days jours', ['days' => $ctx['average_days']]) }}</span> @endif
                                @if ($ctx['outstanding'] > 0) <span class="text-amber-600">{{ __('Impayé : :amount', ['amount' => CurrencyService::format($ctx['outstanding'], $currency)]) }}</span> @endif
                                @if ($ctx['last_invoice_date']) <span>{{ __('Dernière facture : :date', ['date' => $ctx['last_invoice_date']]) }}</span> @endif
                            </div>
                        @endif
                    </div>
                @else
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <input wire:model.live.debounce.300ms="clientSearch" @focus="open = true" type="text" placeholder="{{ __('Rechercher un client…') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                        @error('clientId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @if (count($this->clients) > 0)
                            <div x-show="open" x-transition class="absolute z-20 mt-2 w-full rounded-2xl border border-slate-200 bg-white py-2 shadow-lg">
                                @foreach ($this->clients as $c)
                                    <button type="button" wire:click="selectClient('{{ $c['id'] }}')" @click="open = false" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-slate-50">
                                        <div>
                                            <p class="font-medium text-ink">{{ $c['name'] }}</p>
                                            <p class="text-xs text-slate-600">{{ $c['email'] ?? $c['phone'] ?? $c['sector'] ?? '' }}</p>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <button type="button" wire:click="openClientModal" class="mt-3 inline-flex items-center text-sm font-medium text-primary transition hover:text-primary-strong">
                        <svg class="mr-1.5 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        {{ __('Nouveau client') }}
                    </button>
                @endif
            </section>

            {{-- Invoice info --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Informations') }}</h3>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Référence') }} <span class="text-rose-500">*</span></label>
                        <input wire:model.blur="reference" type="text" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                        @error('reference') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Devise') }}</label>
                        <select wire:model.live="currency" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10">
                            @foreach (CurrencyService::currencies() as $code => $config)
                                <option value="{{ $code }}">{{ $config['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date émission (flatpickr) --}}
                    <div
                        wire:ignore
                        x-data="{
                            picker: null,
                            init() {
                                this.picker = flatpickr(this.$refs.input, {
                                    dateFormat: 'Y-m-d',
                                    altInput: true,
                                    altFormat: 'd/m/Y',
                                    defaultDate: $wire.issuedAt,
                                    onChange: (dates, dateStr) => {
                                        $wire.set('issuedAt', dateStr);
                                    }
                                });
                            },
                            destroy() { if (this.picker) this.picker.destroy(); }
                        }"
                    >
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __("Date d'émission") }} <span class="text-rose-500">*</span></label>
                        <input x-ref="input" type="text" readonly class="w-full cursor-pointer rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                        @error('issuedAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Échéance --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Échéance') }} <span class="text-rose-500">*</span></label>
                        <div class="mb-2 flex flex-wrap gap-2.5">
                            @foreach ([['0', __('À réception')], ['7', '7j'], ['15', '15j'], ['30', '30j'], ['custom', __('Autre date')]] as [$val, $label])
                                <button type="button" wire:click="$set('dueDatePreset', '{{ $val }}')" class="rounded-full border px-3 py-1 text-xs font-medium transition {{ $dueDatePreset === $val ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:border-primary/30 hover:text-primary' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div
                            wire:ignore
                            x-data="{
                                picker: null,
                                init() {
                                    this.picker = flatpickr(this.$refs.dueInput, {
                                        dateFormat: 'Y-m-d',
                                        altInput: true,
                                        altFormat: 'd/m/Y',
                                        defaultDate: $wire.dueAt,
                                        minDate: $wire.issuedAt,
                                        onChange: (dates, dateStr) => {
                                            $wire.set('dueAt', dateStr);
                                            $wire.set('dueDatePreset', 'custom');
                                        }
                                    });
                                    this.$watch('$wire.dueAt', (val) => {
                                        if (this.picker && val) this.picker.setDate(val, false);
                                    });
                                    this.$watch('$wire.issuedAt', (val) => {
                                        if (this.picker && val) this.picker.set('minDate', val);
                                    });
                                },
                                destroy() { if (this.picker) this.picker.destroy(); }
                            }"
                        >
                            <input x-ref="dueInput" type="text" readonly class="w-full cursor-pointer rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                        </div>
                        @error('dueAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                </div>
            </section>

            {{-- Invoice lines --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Lignes de facture') }}</h3>
                @error('lines') <p class="mb-3 text-xs text-rose-600">{{ $message }}</p> @enderror

                <div class="space-y-5">
                    @foreach ($lines as $index => $line)
                        <div wire:key="line-{{ $index }}" class="rounded-2xl border border-slate-200 bg-slate-50/30 p-5">
                            <div class="flex flex-wrap items-start gap-3">
                                {{-- Désignation --}}
                                <div class="w-full min-w-0 md:flex-1">
                                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('Désignation') }}</label>
                                    <input wire:model.blur="lines.{{ $index }}.description" type="text" placeholder="{{ __('Ex : Ciment, prestation…') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                                    @error("lines.{$index}.description") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                {{-- Quantité --}}
                                <div class="w-full md:w-20 md:shrink-0">
                                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('Qté') }}</label>
                                    <input wire:model.blur="lines.{{ $index }}.quantity" type="number" min="1" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-ink tabular-nums focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                                </div>

                                {{-- Prix unitaire --}}
                                <div
                                    class="w-full md:w-32 md:shrink-0"
                                    x-data="{
                                        raw: {{ min((int) ($line['unit_price'] ?? 0), CurrencyService::maxAmount($this->currency)) }},
                                        formatted: '',
                                        get noDecimals() { return $wire.currencyJs.decimals === 0; },
                                        get maxRaw() { return $wire.currencyJs.maxAmount; },
                                        formatNoDecimal(v) {
                                            return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, $wire.currencyJs.thousandsSep);
                                        },
                                        clamp(v) { return Math.min(Math.max(v, 0), this.maxRaw); },
                                        onInput(e) {
                                            if (this.noDecimals) {
                                                this.raw = this.clamp(parseInt(e.target.value.replace(/\D/g, '')) || 0);
                                                this.formatted = this.formatNoDecimal(this.raw);
                                                e.target.value = this.formatted;
                                            } else {
                                                let v = e.target.value.replace(/[^\d.]/g, '');
                                                this.raw = this.clamp(Math.round(parseFloat(v || '0') * Math.pow(10, $wire.currencyJs.decimals)));
                                            }
                                            $wire.set('lines.{{ $index }}.unit_price', this.raw);
                                        },
                                        init() {
                                            if (this.noDecimals) {
                                                this.formatted = this.raw > 0 ? this.formatNoDecimal(this.raw) : '';
                                            } else {
                                                this.formatted = this.raw > 0 ? (this.raw / Math.pow(10, $wire.currencyJs.decimals)).toFixed($wire.currencyJs.decimals) : '';
                                            }
                                            this.$watch('$wire.currencyJs', () => {
                                                if (this.noDecimals) {
                                                    this.formatted = this.raw > 0 ? this.formatNoDecimal(this.raw) : '';
                                                } else {
                                                    this.formatted = this.raw > 0 ? (this.raw / Math.pow(10, $wire.currencyJs.decimals)).toFixed($wire.currencyJs.decimals) : '';
                                                }
                                            });
                                        }
                                    }"
                                >
                                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('P.U.') }} (<span x-text="$wire.currencyJs.label"></span>)</label>
                                    <input
                                        type="text"
                                        :inputmode="noDecimals ? 'numeric' : 'decimal'"
                                        :value="formatted"
                                        @input="onInput($event)"
                                        placeholder="0"
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-ink tabular-nums focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                    />
                                </div>

                                {{-- Total ligne --}}
                                <div class="w-full md:w-36 md:shrink-0"
                                     x-data="{
                                         get c() { return $wire.currencyJs; },
                                         get total() {
                                             let qty = parseInt($wire.lines[{{ $index }}]?.quantity) || 0;
                                             let price = parseInt($wire.lines[{{ $index }}]?.unit_price) || 0;
                                             return qty * price;
                                         },
                                         get display() {
                                             let t = this.total;
                                             if (this.c.decimals > 0) {
                                                 let v = (t / Math.pow(10, this.c.decimals)).toFixed(this.c.decimals);
                                                 return v;
                                             }
                                             return t.toString().replace(/\B(?=(\d{3})+(?!\d))/g, this.c.thousandsSep);
                                         }
                                     }"
                                >
                                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('Total') }} (<span x-text="$wire.currencyJs.label"></span>)</label>
                                    <input type="text" disabled :value="display" tabindex="-1" class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-right text-sm font-bold tabular-nums text-ink" />
                                </div>

                                {{-- Delete button (inline, with tooltip) --}}
                                @if (count($lines) > 1)
                                    <div class="relative mt-2 md:mt-6 shrink-0" x-data="{ show: false }">
                                        <button
                                            type="button"
                                            wire:click="removeLine({{ $index }})"
                                            @mouseenter="show = true"
                                            @mouseleave="show = false"
                                            class="rounded-xl border border-transparent p-2 text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                        >
                                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                        </button>
                                        <div x-show="show" x-transition class="absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink px-2.5 py-1.5 text-xs font-medium text-white shadow-lg">
                                            {{ __('Supprimer') }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">
                    <button type="button" wire:click="addLine" class="inline-flex w-full items-center justify-center rounded-2xl border border-dashed border-slate-300 py-3 text-sm font-medium text-primary transition hover:border-primary/40 hover:bg-primary/5">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        {{ __('Ajouter une ligne') }}
                    </button>
                </div>
            </section>

            {{-- Montants et taxes --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-1 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Montants et taxes') }}</h3>
                <p class="mb-5 text-xs text-slate-600">{{ __('Ajustez la remise et la TVA. Le total se met à jour automatiquement.') }}</p>

                @php $totals = $this->computedTotals; @endphp

                <div class="flex flex-col gap-8 md:flex-row md:items-start">
                    {{-- Left: Ajustements --}}
                    <div class="space-y-6 md:w-[55%]">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-600">{{ __('Ajustements') }}</p>

                        {{-- Remise globale --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-800">{{ __('Remise globale') }}</label>
                            <div class="flex items-center gap-0.5">
                                <span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-200 bg-slate-100 px-3 py-2.5 text-xs font-semibold text-slate-700">%</span>
                                <input wire:model.live="discount" type="number" min="0" max="100" placeholder="0" class="w-20 rounded-r-xl border border-slate-200 bg-white px-3 py-2.5 text-center text-sm text-ink tabular-nums focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            </div>
                            <p class="mt-1.5 text-xs text-slate-600">{{ __('Appliquée sur le sous-total HT') }}</p>
                        </div>

                        {{-- TVA --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-800">{{ __('TVA') }}</label>
                            <div class="inline-flex overflow-hidden rounded-xl border border-slate-200">
                                <button type="button" wire:click="$set('taxMode', '0')" class="border-r border-slate-200 px-4 py-2.5 text-sm font-medium transition {{ $taxMode === '0' ? 'bg-primary/10 text-primary' : 'bg-white text-slate-700 hover:bg-slate-50' }}">{{ __('Sans TVA') }}</button>
                                <button type="button" wire:click="$set('taxMode', '18')" class="border-r border-slate-200 px-4 py-2.5 text-sm font-medium transition {{ $taxMode === '18' ? 'bg-primary/10 text-primary' : 'bg-white text-slate-700 hover:bg-slate-50' }}">18 %</button>
                                <button type="button" wire:click="$set('taxMode', 'custom')" class="px-4 py-2.5 text-sm font-medium transition {{ $taxMode === 'custom' ? 'bg-primary/10 text-primary' : 'bg-white text-slate-700 hover:bg-slate-50' }}">{{ __('Autre') }}</button>
                            </div>
                            @if ($taxMode === 'custom')
                                <div class="mt-3 flex items-center gap-2">
                                    <label class="text-xs font-medium text-slate-700">{{ __('Taux personnalisé') }}</label>
                                    <input wire:model.live="customTaxRate" type="number" min="0" max="100" class="w-20 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-ink tabular-nums focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                                    <span class="text-sm text-slate-700">%</span>
                                </div>
                            @endif
                            <p class="mt-1.5 text-xs text-slate-600">{{ __('La TVA est calculée après application de la remise.') }}</p>
                        </div>
                    </div>

                    {{-- Vertical separator (desktop) --}}
                    <div class="hidden self-stretch border-l border-slate-100 md:block"></div>

                    {{-- Right: Récapitulatif --}}
                    <div class="md:w-[45%]">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-600">{{ __('Récapitulatif') }}</p>

                        <div class="space-y-3 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-slate-600">{{ __('Sous-total HT') }}</span>
                                <span class="whitespace-nowrap font-medium tabular-nums text-ink">{{ CurrencyService::format($totals['subtotal'], $currency) }}</span>
                            </div>
                            @if ($totals['discount_amount'] > 0)
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-slate-600">{{ __('Remise (:rate%)', ['rate' => $discount]) }}</span>
                                    <span class="whitespace-nowrap tabular-nums text-rose-600">-{{ CurrencyService::format($totals['discount_amount'], $currency) }}</span>
                                </div>
                            @endif
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-slate-600">{{ __('TVA (:rate%)', ['rate' => $taxRate]) }}</span>
                                <span class="whitespace-nowrap tabular-nums text-ink">{{ CurrencyService::format($totals['tax_amount'], $currency) }}</span>
                            </div>

                            <hr class="border-slate-200">

                            <div class="flex items-baseline justify-between gap-3 pt-1">
                                <span class="text-base font-semibold text-ink">{{ __('Total TTC') }}</span>
                                <span class="whitespace-nowrap text-2xl font-bold tabular-nums text-ink">{{ CurrencyService::format($totals['total'], $currency) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Paiement --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-1 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Paiement') }}</h3>
                <p class="mb-5 text-xs text-slate-500">{{ __('Sélectionnez le moyen de paiement accepté pour cette facture.') }}</p>

                <div class="space-y-5">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-800">{{ __('Moyen de paiement') }}</label>
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                            @foreach ([
                                ['wave', 'Wave'],
                                ['orange_money', 'Orange Money'],
                                ['cash', __('Espèces')],
                                ['bank_transfer', __('Virement bancaire')],
                            ] as [$val, $label])
                                <button
                                    type="button"
                                    wire:click="$set('paymentMethod', '{{ $val }}')"
                                    class="rounded-xl border px-3 py-3 text-center text-sm font-medium transition {{ $paymentMethod === $val ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:border-primary/30 hover:bg-slate-50' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if ($paymentMethod === 'bank_transfer')
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('RIB / Coordonnées bancaires') }}</label>
                            <textarea wire:model.blur="paymentDetails" rows="3" placeholder="{{ __('Ex : Banque, Code banque, N° de compte, Clé RIB…') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"></textarea>
                        </div>
                    @endif

                    @if ($paymentMethod === 'wave' || $paymentMethod === 'orange_money')
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Numéro de réception') }}</label>
                            <input wire:model.blur="paymentDetails" type="text" placeholder="{{ __('Ex : +221 77 123 45 67') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                        </div>
                    @endif
                </div>
            </section>

            {{-- Relances --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-1 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Relances') }}</h3>
                <p class="mb-5 text-xs text-slate-500">{{ __('Configurez les relances automatiques envoyées au client autour de l\'échéance.') }}</p>

                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2.5">
                        @foreach ([
                            ['-7', __('J-7')],
                            ['-2', __('J-2')],
                            ['0', __('Jour J')],
                            ['+7', __('J+7')],
                            ['+15', __('J+15')],
                            ['+30', __('J+30')],
                        ] as [$val, $label])
                            <button
                                type="button"
                                wire:click="toggleReminder('{{ $val }}')"
                                class="rounded-xl border px-4 py-2.5 text-sm font-medium transition {{ in_array($val, $reminderSchedule) ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:border-primary/30 hover:bg-slate-50' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    @if (count($reminderSchedule) > 0)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <svg class="mr-1.5 inline size-4 text-teal" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            {{ __(':count relance(s) configurée(s) autour de l\'échéance.', ['count' => count($reminderSchedule)]) }}
                        </div>
                    @else
                        <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            <svg class="mr-1.5 inline size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                            {{ __('Aucune relance configurée. Le client ne sera pas relancé automatiquement.') }}
                        </div>
                    @endif
                </div>
            </section>

            {{-- Notes & conditions --}}
            <section class="app-shell-panel p-6" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Notes') }}</h3>
                    <svg class="size-5 text-slate-500 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <div x-show="open" x-collapse class="mt-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Notes (visible sur la facture)') }}</label>
                        <textarea wire:model.blur="notes" rows="2" placeholder="{{ __('Ex : Merci pour votre confiance…') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"></textarea>
                    </div>
                </div>
            </section>
        </div>

        {{-- RIGHT COLUMN (sticky sidebar) --}}
        <div class="lg:col-span-1">
            <div class="sticky top-6 space-y-5">
                {{-- Bloc 1: Résumé --}}
                <section class="app-shell-panel p-6">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Résumé') }}</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="shrink-0 text-slate-700">{{ __('Référence') }}</span>
                            <span class="truncate text-right font-medium text-ink">{{ $reference }}</span>
                        </div>
                        @if ($this->selectedClient)
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="shrink-0 text-slate-700">{{ __('Client') }}</span>
                                <span class="truncate text-right font-medium text-ink">{{ $this->selectedClient->name }}</span>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="shrink-0 text-slate-700">{{ __('Émission') }}</span>
                            <span class="whitespace-nowrap text-ink">{{ $issuedAt ? Carbon::parse($issuedAt)->locale('fr_FR')->translatedFormat('d M Y') : '—' }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="shrink-0 text-slate-700">{{ __('Échéance') }}</span>
                            <span class="whitespace-nowrap text-ink">{{ $this->formattedDueDate ?: '—' }}</span>
                        </div>
                    </div>
                </section>

                {{-- Bloc 2: Total (scoreboard) --}}
                <section class="app-shell-panel p-6">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Total') }}</h3>
                    @php $totals = $this->computedTotals; @endphp
                    <div class="space-y-2.5 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="shrink-0 text-slate-700">{{ __('Sous-total HT') }}</span>
                            <span class="whitespace-nowrap font-medium tabular-nums text-ink">{{ CurrencyService::format($totals['subtotal'], $currency) }}</span>
                        </div>
                        @if ($totals['discount_amount'] > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="shrink-0 text-slate-700">{{ __('Remise (:rate%)', ['rate' => $discount]) }}</span>
                                <span class="whitespace-nowrap tabular-nums text-rose-600">-{{ CurrencyService::format($totals['discount_amount'], $currency) }}</span>
                            </div>
                        @endif
                        @if ($totals['tax_amount'] > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="shrink-0 text-slate-700">{{ __('TVA (:rate%)', ['rate' => $taxRate]) }}</span>
                                <span class="whitespace-nowrap tabular-nums text-ink">{{ CurrencyService::format($totals['tax_amount'], $currency) }}</span>
                            </div>
                        @endif
                        <hr class="border-slate-200">
                        <div class="flex items-baseline justify-between gap-3 pt-1">
                            <span class="shrink-0 text-base font-semibold text-ink">{{ __('Total TTC') }}</span>
                            <span class="whitespace-nowrap text-xl font-bold tabular-nums text-ink">{{ CurrencyService::format($totals['total'], $currency) }}</span>
                        </div>
                    </div>
                </section>

                {{-- Bloc 3: Suivi Fayeku --}}
                <section class="app-shell-panel p-5">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Suivi') }}</h3>
                    <div class="space-y-2.5 text-sm">
                        @if ($paymentMethod)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-slate-600">{{ __('Paiement') }}</span>
                                <span class="text-xs font-medium text-ink">{{ match($paymentMethod) { 'wave' => 'Wave', 'orange_money' => 'Orange Money', 'cash' => __('Espèces'), 'bank_transfer' => __('Virement'), default => '—' } }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-600">{{ __('Relances Fayeku') }}</span>
                            @if (count($reminderSchedule) > 0)
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-teal">
                                    <span class="size-1.5 rounded-full bg-teal"></span>
                                    {{ __(':count activée(s)', ['count' => count($reminderSchedule)]) }}
                                </span>
                            @else
                                <span class="text-xs font-medium text-slate-500">{{ __('Désactivées') }}</span>
                            @endif
                        </div>
                        @if ($this->selectedClient)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-slate-600">{{ __('Contact') }}</span>
                                <span class="truncate text-right text-xs text-ink">{{ $this->selectedClient->email ?? $this->selectedClient->phone ?? '—' }}</span>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Bloc 4: Actions --}}
                <section class="app-shell-panel space-y-3 p-5">
                    <button type="button" wire:click="previewPdf" class="flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        {{ __('Aperçu PDF') }}
                    </button>
                    <button type="button" wire:click="saveDraft" class="flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                        {{ __('Enregistrer brouillon') }}
                    </button>
                    <button type="button" wire:click="openSendModal" class="flex w-full items-center justify-center rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                        {{ __('Envoyer la facture') }}
                    </button>
                </section>

                {{-- Mobile fixed bottom bar --}}
                <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white px-6 py-4 lg:hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-600">{{ __('Total TTC') }}</p>
                            <p class="text-lg font-bold tabular-nums text-ink">{{ CurrencyService::format($totals['total'], $currency) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="saveDraft" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800">{{ __('Brouillon') }}</button>
                            <button type="button" wire:click="openSendModal" class="rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white">{{ __('Envoyer') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Client creation modal --}}
    @if ($showClientModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showClientModal', false)" x-data @keydown.escape.window="$wire.set('showClientModal', false)">
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <form wire:submit="saveClient">
                    <div class="flex items-start justify-between border-b border-slate-100 px-7 py-6">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">{{ __('Nouveau client') }}</h2>
                            <p class="mt-1 text-sm text-slate-700">{{ __('Créez un client sans quitter votre facture.') }}</p>
                        </div>
                        <button type="button" wire:click="$set('showClientModal', false)" class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-700">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="max-h-[70vh] overflow-y-auto px-7 py-6">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Nom du client') }} <span class="text-rose-500">*</span></label>
                                <input wire:model="clientName" type="text" required autofocus class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                                @error('clientName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Secteur') }}</label>
                                <select wire:model="clientSector" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10">
                                    <option value="">{{ __('Choisir un secteur…') }}</option>
                                    <option>Agriculture, Élevage &amp; Pêche</option>
                                    <option>Agroalimentaire &amp; Transformation</option>
                                    <option>Commerce de gros</option>
                                    <option>Commerce de détail &amp; Distribution</option>
                                    <option>Bâtiment &amp; Travaux Publics</option>
                                    <option>Transport &amp; Logistique</option>
                                    <option>Télécommunications</option>
                                    <option>Technologies de l'information &amp; Communication</option>
                                    <option>Industrie manufacturière</option>
                                    <option>Énergie, Mines &amp; Pétrole</option>
                                    <option>Santé &amp; Pharmacie</option>
                                    <option>Éducation &amp; Formation</option>
                                    <option>Immobilier &amp; Foncier</option>
                                    <option>Finance, Banque &amp; Assurance</option>
                                    <option>Hôtellerie &amp; Restauration</option>
                                    <option>Tourisme &amp; Loisirs</option>
                                    <option>Artisanat &amp; Arts</option>
                                    <option>Médias &amp; Communication</option>
                                    <option>Textile, Habillement &amp; Cuir</option>
                                    <option>Services aux entreprises &amp; Conseil</option>
                                    <option>Environnement &amp; Eau</option>
                                    <option value="Autre">{{ __('Autre') }}</option>
                                </select>
                            </div>
                            <div
                                wire:ignore
                                x-data="{
                                    iti: null,
                                    init() {
                                        this.iti = window.intlTelInput(this.$refs.phoneInput, {
                                            initialCountry: '{{ strtolower($clientPhoneCountry) }}',
                                            preferredCountries: ['sn', 'ci', 'fr', 'ml', 'bf', 'gn', 'tg', 'bj', 'ne', 'cm'],
                                            separateDialCode: true,
                                            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@26/build/js/utils.js',
                                            containerClass: 'iti--fayeku w-full',
                                        });
                                        this.$refs.phoneInput.addEventListener('countrychange', () => {
                                            const data = this.iti.getSelectedCountryData();
                                            $wire.clientPhoneCountry = data.iso2.toUpperCase();
                                        });
                                    },
                                    syncPhone() {
                                        const number = this.iti.getNumber();
                                        $wire.clientPhone = number || '';
                                    },
                                    destroy() { if (this.iti) this.iti.destroy(); }
                                }"
                            >
                                <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Téléphone / WhatsApp') }}</label>
                                <input
                                    x-ref="phoneInput"
                                    type="tel"
                                    @input="syncPhone()"
                                    @blur="syncPhone()"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Email') }}</label>
                                <input wire:model="clientEmail" type="email" placeholder="contact@client.sn" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                                @error('clientEmail') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Identifiant fiscal') }}</label>
                                <input wire:model="clientTaxId" type="text" placeholder="NINEA / RCCM / NCC" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Adresse') }}</label>
                                <input wire:model="clientAddress" type="text" placeholder="{{ __('Rue, quartier, ville…') }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                        <button type="button" wire:click="$set('showClientModal', false)" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">{{ __('Annuler') }}</button>
                        <button type="submit" class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">{{ __('Créer le client') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Send modal --}}
    @if ($showSendModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showSendModal', false)" x-data @keydown.escape.window="$wire.set('showSendModal', false)">
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-7 py-6">
                    <div>
                        <h2 class="text-lg font-semibold text-ink">{{ __('Envoyer la facture') }}</h2>
                        <p class="mt-1 text-sm text-slate-700">{{ $reference }} &middot; {{ CurrencyService::format($this->computedTotals['total'], $currency) }}</p>
                    </div>
                    <button type="button" wire:click="$set('showSendModal', false)" class="ml-4 shrink-0 rounded-full border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="space-y-5 px-7 py-6">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-800">{{ __("Canal d'envoi") }}</label>
                        <div class="flex gap-3">
                            @foreach ([['email', 'Email'], ['whatsapp', 'WhatsApp'], ['pdf', __('Télécharger PDF')]] as [$val, $label])
                                <button type="button" wire:click="$set('sendChannel', '{{ $val }}')" class="flex-1 rounded-xl border px-3 py-2.5 text-center text-sm font-medium transition {{ $sendChannel === $val ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:border-primary/30' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    @if ($sendChannel !== 'pdf')
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Destinataire') }}</label>
                            <input wire:model="sendRecipient" type="text" placeholder="{{ $sendChannel === 'email' ? 'email@client.sn' : '+221 XX XXX XX XX' }}" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10" />
                        </div>
                    @endif
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Message') }}</label>
                        <textarea wire:model="sendMessage" rows="4" class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"></textarea>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <svg class="mr-1 inline size-4 text-teal" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        {{ __("Cette facture sera suivie par Fayeku après l'échéance.") }}
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50/50 px-7 py-4">
                    <button type="button" wire:click="$set('showSendModal', false)" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">{{ __('Annuler') }}</button>
                    <button type="button" wire:click="send" class="inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                        {{ __('Envoyer maintenant') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel confirmation modal --}}
    @if ($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/75 p-4" wire:click.self="$set('showCancelModal', false)" x-data @keydown.escape.window="$wire.set('showCancelModal', false)">
            <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all sm:p-8">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 sm:mx-0 sm:size-10">
                        <svg class="size-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-base font-semibold text-ink">{{ __('Quitter sans enregistrer ?') }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ __('Vous avez des modifications non enregistrées. Si vous quittez maintenant, vos changements seront perdus.') }}</p>
                    </div>
                </div>
                <div class="mt-6 sm:flex sm:flex-row-reverse sm:gap-3">
                    <button type="button" wire:click="cancel" class="inline-flex w-full justify-center rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-500 sm:w-auto">{{ __('Quitter') }}</button>
                    <button type="button" wire:click="$set('showCancelModal', false)" class="mt-3 inline-flex w-full justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 sm:mt-0 sm:w-auto">{{ __('Continuer l\'édition') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
