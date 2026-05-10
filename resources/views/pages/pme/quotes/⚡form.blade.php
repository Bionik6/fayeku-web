<?php

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Http\Controllers\PME\ProposalDocumentPreviewController;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\ProposalDocument;
use App\Services\PME\CurrencyService;
use App\Services\PME\ProposalDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Devis')]
#[Layout('layouts::pme')]
class extends Component {

    public ?ProposalDocument $quote = null;

    public bool $isEditing = false;

    public ?Company $company = null;

    public string $reference = '';

    public string $clientId = '';

    public string $clientSearch = '';

    public string $issuedAt = '';

    public string $validUntil = '';

    public string $currency = 'XOF';

    public int $taxRate = 0;

    public bool $hasTax = false;

    public bool $hasDiscount = false;

    public ?int $discount = 0;

    public string $discountType = 'percent';

    public int $customTaxRate = 0;

    public string $taxMode = '18';

    public bool $hasNotes = false;

    public string $notes = '';

    public string $validityPreset = '30';

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public bool $showCancelModal = false;

    public bool $showSaveDraftModal = false;

    public ?string $lastSavedAt = null;

    /** @var array{decimals: int, decSep: string, thousandsSep: string, label: string} */
    public array $currencyJs = [];

    public function updatedCurrency(): void
    {
        $this->currencyJs = CurrencyService::jsConfig($this->currency);
    }

    public function mount(?ProposalDocument $quote = null): void
    {
        $this->company = auth()->user()->smeCompany();

        abort_unless($this->company, 403);

        if ($quote && $quote->exists) {
            abort_unless($quote->isQuote(), 404);
            abort_unless(auth()->user()->can('update', $quote), 403);
            abort_unless($quote->company_id === $this->company->id, 403);

            $service = app(ProposalDocumentService::class);

            if (! $service->canEdit($quote)) {
                $this->dispatch('toast', type: 'error', title: __('Ce devis ne peut plus être modifié.'));
                $this->redirect(route('pme.quotes.index'), navigate: true);

                return;
            }

            $this->quote = $quote->load('lines', 'client');
            $this->isEditing = true;
            $this->reference = $quote->reference ?? '';
            $this->clientId = $quote->client_id ?? '';
            $this->issuedAt = $quote->issued_at?->format('Y-m-d') ?? '';
            $this->validUntil = $quote->valid_until?->format('Y-m-d') ?? '';
            $this->currency = $quote->currency ?? 'XOF';
            $this->discount = $quote->discount ?? 0;
            $this->discountType = $quote->discount_type ?? 'percent';
            $this->hasDiscount = ($this->discount ?? 0) > 0;
            $this->notes = $quote->notes ?? '';
            $this->hasNotes = $this->notes !== '';
            $this->validityPreset = 'custom';

            $firstLine = $quote->lines->first();
            $rate = $firstLine?->tax_rate ?? 18;
            $this->taxRate = $rate;
            $this->hasTax = $rate > 0;

            if ($rate === 18 || $rate === 0) {
                $this->taxMode = '18';
            }
            else {
                $this->taxMode = 'custom';
                $this->customTaxRate = $rate;
            }

            $this->lines = $quote->lines->map(fn($line) => [
                'description' => $line->description,
                'quantity'    => $line->quantity,
                'unit_price'  => $line->unit_price,
            ])->toArray();
        }
        else {
            abort_unless(auth()->user()->can('create', ProposalDocument::class), 403);

            $this->reference = app(ProposalDocumentService::class)
                ->generateReference($this->company, ProposalDocumentType::Quote);
            $this->issuedAt = now()->format('Y-m-d');
            $this->validUntil = now()->addDays(30)->format('Y-m-d');
            $this->lines = [$this->emptyLine()];
        }

        $this->currencyJs = CurrencyService::jsConfig($this->currency);
    }

    #[Computed]
    public function clients(): array
    {
        if (!$this->company) {
            return [];
        }

        $query = Client::query()->where('company_id', $this->company->id);

        if ($this->clientSearch !== '') {
            $term = '%' . mb_strtolower(trim($this->clientSearch)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
            });
        }

        return $query->orderBy('name')->limit(10)->get([
                'id',
                'name',
                'email',
                'phone',
            ])->toArray();
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

    #[Computed]
    public function computedTotals(): array
    {
        $discount = $this->hasDiscount ? ($this->discount ?? 0) : 0;

        return app(ProposalDocumentService::class)->calculateTotals($this->lines, $this->taxRate, $discount, $this->discountType);
    }

    #[Computed]
    public function formattedValidUntil(): string
    {
        if ($this->validUntil === '') {
            return '';
        }

        try {
            return format_date(Carbon::parse($this->validUntil));
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

        if ($this->hasNotes && $this->notes !== '') {
            return true;
        }

        return $this->hasDiscount && ($this->discount ?? 0) > 0;
    }

    public function confirmCancel(): void
    {
        if ($this->hasFormData) {
            $this->showCancelModal = true;

            return;
        }

        $this->redirect(route('pme.quotes.index'), navigate: true);
    }

    public function cancel(): void
    {
        $this->redirect(route('pme.quotes.index'), navigate: true);
    }

    public function updatedHasDiscount(bool $value): void
    {
        if (! $value) {
            $this->discount = 0;
            $this->resetErrorBag('discount');
        }
    }

    public function updatedHasNotes(bool $value): void
    {
        if (! $value) {
            $this->notes = '';
            $this->resetErrorBag('notes');
        }
    }

    public function updatedDiscountType(): void
    {
        $this->discount = 0;
    }

    public function updatedDiscount(?int $value): void
    {
        if ($this->discountType === 'percent' && $value !== null && $value > 100) {
            $this->discount = 100;
        }
    }

    public function updatedHasTax(bool $value): void
    {
        if (! $value) {
            $this->taxRate = 0;

            return;
        }

        $this->taxRate = match ($this->taxMode) {
            'custom' => max(0, min(100, $this->customTaxRate ?? 0)),
            default => 18,
        };
    }

    public function updatedTaxMode(string $value): void
    {
        if (! $this->hasTax) {
            return;
        }

        $this->taxRate = match ($value) {
            '18' => 18,
            'custom' => $this->customTaxRate,
            default => 18,
        };
    }

    public function updatedCustomTaxRate(?int $value): void
    {
        if ($this->taxMode === 'custom') {
            $this->customTaxRate = max(0, min(100, $value ?? 0));
            if ($this->hasTax) {
                $this->taxRate = $this->customTaxRate;
            }
        }
    }

    public function updatedValidityPreset(string $value): void
    {
        if ($value === 'custom' || $this->issuedAt === '') {
            return;
        }

        $base = Carbon::parse($this->issuedAt);

        $this->validUntil = match ($value) {
            '15' => $base->addDays(15)->format('Y-m-d'),
            '30' => $base->addDays(30)->format('Y-m-d'),
            '60' => $base->addDays(60)->format('Y-m-d'),
            '90' => $base->addDays(90)->format('Y-m-d'),
            default => $this->validUntil,
        };
    }

    public function updatedIssuedAt(): void
    {
        if ($this->validityPreset !== 'custom') {
            $this->updatedValidityPreset($this->validityPreset);
        }
    }

    public function selectClient(string $id): void
    {
        $this->clientId = $id;
        $this->clientSearch = '';
        $this->resetErrorBag('clientId');
    }

    public function clearClient(): void
    {
        $this->clientId = '';
        $this->clientSearch = '';
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

    public function updatedLines(): void
    {
        $this->resetErrorBag('lines');

        foreach (array_keys($this->lines) as $i) {
            $this->resetErrorBag("lines.{$i}.description");
            $this->resetErrorBag("lines.{$i}.quantity");
            $this->resetErrorBag("lines.{$i}.unit_price");
        }
    }

    public function openSaveDraftModal(): void
    {
        try {
            $this->validateForm();
        } catch (ValidationException $e) {
            $this->dispatch('validation-errors', messages: $e->validator->errors()->all());

            throw $e;
        }

        $this->showSaveDraftModal = true;
    }

    public function confirmSaveDraft(): void
    {
        $this->saveDraft(notify: false);
        $this->showSaveDraftModal = false;

        session()->flash('success', __('Brouillon enregistré avec succès.'));
        $this->redirect(route('pme.quotes.index').'?statut=draft', navigate: true);
    }

    public function saveDraft(bool $notify = true): void
    {
        try {
            $this->validateForm();
        } catch (ValidationException $e) {
            $this->dispatch('validation-errors', messages: $e->validator->errors()
                                                                        ->all());

            throw $e;
        }

        $service = app(ProposalDocumentService::class);

        $data = $this->buildData();
        $lines = $this->buildLines();

        if ($this->isEditing) {
            $service->update($this->quote, $data, $lines);
            $this->quote->refresh();
        }
        else {
            $this->quote = $service->create($this->company, ProposalDocumentType::Quote, $data, $lines);
            $this->isEditing = true;
        }

        $this->lastSavedAt = now()->format('H:i');

        if ($notify) {
            $this->dispatch('toast', type: 'success', title: __('Brouillon enregistré.'));
        }
    }

    /**
     * Save the quote as Draft and redirect to its show page with `?send=1`,
     * which auto-opens the send modal (WhatsApp/email + public PDF link).
     * The Draft → Sent transition happens when the user clicks "Envoyer
     * maintenant" inside that modal.
     */
    public function saveAndSend(): void
    {
        $totals = $this->computedTotals;

        if ($totals['total'] <= 0) {
            $this->addError('lines', __('Le montant du devis doit être supérieur à 0.'));
            $this->dispatch('toast', type: 'warning', title: __('Le montant du devis doit être supérieur à 0.'));

            return;
        }

        $this->saveDraft(notify: false);

        if ($this->quote) {
            $this->redirect(route('pme.quotes.show', $this->quote).'?send=1', navigate: true);
        }
    }

    public function previewPdf(): void
    {
        try {
            $this->validateForm();
        } catch (ValidationException $e) {
            $this->dispatch('validation-errors', messages: $e->validator->errors()->all());

            throw $e;
        }

        $tempId = (string) Str::uuid();

        Cache::put(
            ProposalDocumentPreviewController::QUOTE_CACHE_PREFIX.$tempId,
            [
                'company_id' => $this->company->id,
                'data' => $this->buildData(),
                'lines' => $this->buildLines(),
            ],
            now()->addMinutes(15),
        );

        $this->dispatch('open-pdf', url: route('pme.quotes.preview', ['tempId' => $tempId]));
    }

    #[On('client-created')]
    public function onClientCreated(string $id): void
    {
        $this->selectClient($id);
    }

    private function validateForm(): void
    {
        $this->validate([
            'clientId'            => ['required', 'string', 'exists:clients,id'],
            'reference'           => ['required', 'string', 'max:50'],
            'issuedAt'            => ['required', 'date'],
            'validUntil'          => ['required', 'date', 'after_or_equal:issuedAt'],
            'currency'            => [
                'required',
                'string',
                'in:' . implode(',', CurrencyService::codes())
            ],
            'taxRate'             => ['required', 'integer', 'min:0', 'max:100'],
            'discountType'        => ['required', 'string', 'in:percent,fixed'],
            'discount'            => $this->discountType === 'fixed'
                ? ['nullable', 'integer', 'min:0', 'max:' . CurrencyService::maxAmount($this->currency)]
                : ['nullable', 'integer', 'min:0', 'max:100'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'lines'               => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity'    => ['required', 'integer', 'min:1'],
            'lines.*.unit_price'  => [
                'required',
                'integer',
                'min:0',
                'max:' . CurrencyService::maxAmount($this->currency)
            ],
        ], [
            'clientId.required'            => __('Veuillez sélectionner un client.'),
            'reference.required'           => __('La référence est requise.'),
            'issuedAt.required'            => __("La date d'émission est requise."),
            'validUntil.required'          => __('La date de validité est requise.'),
            'validUntil.after_or_equal'    => __('La date de validité ne peut pas être antérieure à la date d\'émission.'),
            'lines.required'               => __('Ajoutez au moins une ligne à votre devis.'),
            'lines.min'                    => __('Ajoutez au moins une ligne à votre devis.'),
            'lines.*.description.required' => __('La désignation est requise.'),
            'lines.*.quantity.required'    => __('La quantité est requise.'),
            'lines.*.quantity.min'         => __('La quantité doit être au moins 1.'),
            'lines.*.unit_price.required'  => __('Le prix unitaire est requis.'),
            'lines.*.unit_price.max'       => __('Le prix unitaire ne peut pas dépasser 999 999 999.'),
        ]);
    }

    private function buildData(): array
    {
        return [
            'client_id'   => $this->clientId,
            'reference'   => $this->reference,
            'currency'    => $this->currency,
            'issued_at'   => $this->issuedAt,
            'valid_until' => $this->validUntil,
            'tax_rate'      => $this->taxRate,
            'discount'      => $this->hasDiscount ? ($this->discount ?? 0) : 0,
            'discount_type' => $this->discountType,
            'notes'         => $this->hasNotes ? $this->emptyToNull($this->notes) : null,
        ];
    }

    private function buildLines(): array
    {
        return collect($this->lines)->map(fn(array $line) => [
            'description' => $line['description'],
            'quantity'    => (int) $line['quantity'],
            'unit_price'  => (int) $line['unit_price'],
        ])->toArray();
    }

    private function emptyLine(): array
    {
        return [
            'description' => '',
            'quantity'    => 1,
            'unit_price'  => 0,
        ];
    }

    private function emptyToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 pb-24 lg:pb-6"
     x-on:open-pdf.window="window.open($event.detail.url, '_blank')">
    {{-- Header --}}
    <section class="app-shell-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-ink">
                        {{ $isEditing ? __('Modifier le devis') : __('Nouveau devis') }}
                    </h2>
                    @if ($isEditing && $quote)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $reference }}</span>
                    @endif
                </div>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-700">
                    @if ($isEditing && $quote?->status === ProposalDocumentStatus::Sent)
                        <span class="inline-flex items-center gap-1.5"><span
                                    class="size-2 rounded-full bg-blue-400"></span>{{ __('Envoyé') }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5"><span
                                    class="size-2 rounded-full bg-amber-400"></span>{{ __('Brouillon') }}</span>
                    @endif
                    @if ($lastSavedAt)
                        <span class="text-sm text-slate-600">{{ __('Sauvegardé à :time', ['time' => $lastSavedAt]) }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center">
                <button type="button" wire:click="confirmCancel"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">{{ __('Annuler') }}</button>
            </div>
        </div>
        @if ($isEditing && $quote?->status === ProposalDocumentStatus::Sent)
            <div class="border-t border-amber-100 bg-amber-50 px-6 py-3 text-sm text-amber-800">
                <svg class="mr-1.5 inline size-4" fill="none" stroke="currentColor"
                     stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                {{ __('Ce devis a déjà été envoyé. Toute modification impactera le document.') }}
            </div>
        @endif
    </section>

    {{-- Main content: 2-column grid --}}
    <div class="grid gap-6 xl:grid-cols-3">
        {{-- LEFT COLUMN --}}
        <div class="space-y-6 xl:col-span-2">

            {{-- Client block --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Client') }}</h3>
                @if ($clientId && $this->selectedClient)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                        <div class="flex items-start justify-between">
                            <p class="font-semibold text-ink">{{ $this->selectedClient->name }}</p>
                            <button type="button" wire:click="clearClient"
                                    class="ml-3 shrink-0 rounded-full border border-slate-200 bg-white p-2 text-slate-600 transition hover:bg-rose-50 hover:border-rose-200 hover:text-rose-500"
                                    title="{{ __('Retirer') }}">
                                <svg class="size-4" fill="none" stroke="currentColor"
                                     stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm text-slate-700">
                            @if ($this->selectedClient->email)
                                <span>{{ $this->selectedClient->email }}</span>
                            @endif
                            @if ($this->selectedClient->email && $this->selectedClient->phone)
                                <span class="text-slate-500">·</span>
                            @endif
                            @if ($this->selectedClient->phone)
                                <span>{{ format_phone($this->selectedClient->phone) }}</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="relative" x-data="{ open: false }"
                         @click.outside="open = false">
                        <input wire:model.live.debounce.300ms="clientSearch"
                               @focus="open = true" type="text"
                               placeholder="{{ __('Rechercher un client…') }}"
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"/>
                        @error('clientId') <p
                                class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        @if (count($this->clients) > 0)
                            <div x-show="open" x-transition
                                 class="absolute z-20 mt-2 w-full rounded-2xl border border-slate-200 bg-white py-2 shadow-lg">
                                @foreach ($this->clients as $c)
                                    <button type="button"
                                            wire:click="selectClient('{{ $c['id'] }}')"
                                            @click="open = false"
                                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-slate-50">
                                        <div>
                                            <p class="font-medium text-ink">{{ $c['name'] }}</p>
                                            <p class="text-sm text-slate-600">{{ $c['email'] ?? $c['phone'] ?? '' }}</p>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <button type="button" wire:click="$dispatch('open-create-client-modal')"
                            class="mt-3 inline-flex items-center text-sm font-medium text-primary transition hover:text-primary-strong">
                        <svg class="mr-1.5 size-4" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        {{ __('Nouveau client') }}
                    </button>
                @endif
            </section>

            {{-- Quote info --}}
            <section class="app-shell-panel p-6">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Informations') }}</h3>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Référence') }}
                            <span class="text-rose-500">*</span></label>
                        <input wire:model.live.debounce.300ms="reference" type="text"
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"/>
                        @error('reference') <p
                                class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Devise') }}</label>
                        <x-select-native>
                            <select wire:model.live="currency"
                                    class="col-start-1 row-start-1 w-full appearance-none rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-8 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10">
                                @foreach (CurrencyService::currencies() as $code => $config)
                                    <option value="{{ $code }}">{{ $config['name'] }}</option>
                                @endforeach
                            </select>
                        </x-select-native>
                    </div>

                    {{-- Date emission --}}
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
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __("Date d'émission") }}
                            <span class="text-rose-500">*</span></label>
                        <input x-ref="input" type="text" readonly
                               class="w-full cursor-pointer rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"/>
                        @error('issuedAt') <p
                                class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Validite --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ __('Valide jusqu\'au') }}
                            <span class="text-rose-500">*</span></label>
                        <div class="mb-2 flex flex-wrap gap-2.5">
                            @foreach ([['15', '15j'], ['30', '30j'], ['60', '60j'], ['90', '90j'], ['custom', __('Autre date')]] as [$val, $label])
                                <button type="button"
                                        wire:click="$set('validityPreset', '{{ $val }}')"
                                        class="rounded-full border px-3 py-1 text-sm font-medium transition {{ $validityPreset === $val ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-700 hover:border-primary/30 hover:text-primary' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div
                                wire:ignore
                                x-data="{
                                picker: null,
                                init() {
                                    this.picker = flatpickr(this.$refs.validInput, {
                                        dateFormat: 'Y-m-d',
                                        altInput: true,
                                        altFormat: 'd/m/Y',
                                        defaultDate: $wire.validUntil,
                                        minDate: $wire.issuedAt,
                                        onChange: (dates, dateStr) => {
                                            $wire.set('validUntil', dateStr);
                                            $wire.set('validityPreset', 'custom');
                                        }
                                    });
                                    this.$watch('$wire.validUntil', (val) => {
                                        if (this.picker && val) this.picker.setDate(val, false);
                                    });
                                    this.$watch('$wire.issuedAt', (val) => {
                                        if (this.picker && val) this.picker.set('minDate', val);
                                    });
                                },
                                destroy() { if (this.picker) this.picker.destroy(); }
                            }"
                        >
                            <input x-ref="validInput" type="text" readonly
                                   class="w-full cursor-pointer rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"/>
                        </div>
                        @error('validUntil') <p
                                class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                </div>
            </section>

            {{-- Quote lines --}}
            <x-invoicing.line-items :title="__('Lignes du devis')" :lines="$lines" :currency="$currency" />

            {{-- TVA --}}
            <x-invoicing.amounts-and-taxes
                :has-tax="$hasTax"
                :tax-mode="$taxMode"
                :custom-tax-rate="$customTaxRate"
            />

            {{-- Remise globale --}}
            <x-invoicing.global-discount
                :has-discount="$hasDiscount"
                :discount-type="$discountType"
                :discount="$discount"
                :currency="$currency"
                :currency-label="$this->currencyLabel"
            />

            {{-- Notes --}}
            <section class="app-shell-panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-700">{{ __('Notes') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Ajoutez un message qui apparaîtra sur le devis pour le client.') }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="$toggle('hasNotes')"
                        class="relative flex h-7 w-12 shrink-0 items-center rounded-full transition
                            {{ $hasNotes ? 'bg-primary' : 'bg-slate-300' }}"
                        aria-label="{{ __('Activer les notes') }}"
                    >
                        <span class="absolute size-5 rounded-full bg-white shadow transition-all
                            {{ $hasNotes ? 'left-[1.4rem]' : 'left-1' }}"></span>
                    </button>
                </div>

                @if ($hasNotes)
                    <div class="mt-5">
                        <textarea wire:model.blur="notes" rows="3"
                                  placeholder="{{ __('Ex : Conditions de validité, mentions…') }}"
                                  class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/10"></textarea>
                    </div>
                @endif
            </section>
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="xl:col-span-1">
            <div class="sticky top-6 space-y-5">
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
                            <span class="whitespace-nowrap text-ink">{{ format_date($issuedAt) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="shrink-0 text-slate-700">{{ __('Validité') }}</span>
                            <span class="whitespace-nowrap text-ink">{{ $this->formattedValidUntil ?: '—' }}</span>
                        </div>
                    </div>
                </section>

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
                                <span class="shrink-0 text-slate-700">
                                    @if ($discountType === 'fixed')
                                        {{ __('Remise (montant fixe)') }}
                                    @else
                                        {{ __('Remise (:rate%)', ['rate' => $discount]) }}
                                    @endif
                                </span>
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

                <section class="app-shell-panel space-y-3 p-5">
                    <button type="button" wire:click="previewPdf"
                            class="flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                        </svg>
                        {{ __('Aperçu PDF') }}
                    </button>
                    <button type="button" wire:click="openSaveDraftModal"
                            class="flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:border-primary/30 hover:text-primary">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                        </svg>
                        {{ __('Enregistrer comme brouillon') }}
                    </button>
                    <button type="button" wire:click="saveAndSend"
                            class="flex w-full items-center justify-center rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">
                        <svg class="mr-2 size-4" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                        </svg>
                        {{ $isEditing ? __('Envoyer le devis') : __('Enregistrer et Envoyer le devis') }}
                    </button>
                </section>

                {{-- Mobile fixed bottom bar --}}
                <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white px-6 py-4 lg:hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-600">{{ __('Total TTC') }}</p>
                            <p class="text-lg font-bold tabular-nums text-ink">{{ CurrencyService::format($totals['total'], $currency) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="saveDraft"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800">{{ __('Brouillon') }}</button>
                            <button type="button" wire:click="saveAndSend"
                                    class="rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white">{{ __('Envoyer') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Save draft confirmation modal --}}
    @if ($showSaveDraftModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="$set('showSaveDraftModal', false)" x-data
             @keydown.escape.window="$wire.set('showSaveDraftModal', false)">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
                <div class="flex items-start gap-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <svg class="size-5 text-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('Enregistrer en brouillon') }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500">
                            {{ __('Votre devis sera sauvegardé mais non envoyé. Vous serez redirigé vers la liste des devis où vous pourrez retrouver votre brouillon dans l\'onglet « Brouillon ».') }}
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showSaveDraftModal', false)"
                            class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30 hover:text-primary">
                        {{ __('Continuer l\'édition') }}
                    </button>
                    <button type="button" wire:click="confirmSaveDraft"
                            class="rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-strong">
                        {{ __('Enregistrer') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel modal --}}
    @if ($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="$set('showCancelModal', false)">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl">
                <h3 class="text-lg font-semibold text-ink">{{ __('Quitter sans enregistrer ?') }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ __('Vos modifications non enregistrées seront perdues.') }}</p>
                <div class="mt-6 flex justify-center gap-3">
                    <button type="button" wire:click="$set('showCancelModal', false)"
                            class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-primary/30">{{ __('Continuer') }}</button>
                    <button type="button" wire:click="cancel"
                            class="rounded-2xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">{{ __('Quitter') }}</button>
                </div>
            </div>
        </div>
    @endif

    <livewire:create-client-modal :company="$company" />

</div>
