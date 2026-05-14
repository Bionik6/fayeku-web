<?php

use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?Company $company = null;

    public function mount(?Company $company = null): void
    {
        $this->company = $company ?? Auth::user()?->smeCompany();
    }

    /**
     * @return array<int, array{key:string, label:string, done:bool, href:?string}>
     */
    #[Computed]
    public function items(): array
    {
        $company = $this->company;

        if (! $company) {
            return [];
        }

        $hasClient = $company->clients()->exists();
        $hasInvoice = Invoice::query()->where('company_id', $company->id)->exists();

        return [
            [
                'key' => 'account',
                'label' => __('onboarding.checklist.items.account'),
                'done' => true,
                'href' => null,
            ],
            [
                'key' => 'identity',
                'label' => __('onboarding.checklist.items.identity'),
                'done' => filled($company->address) || filled($company->ninea) || filled($company->legal_form),
                'href' => route('pme.settings.index'),
            ],
            [
                'key' => 'signature',
                'label' => __('onboarding.checklist.items.signature'),
                'done' => filled($company->sender_name),
                'href' => route('pme.settings.index', ['section' => 'signature']),
            ],
            [
                'key' => 'first_client',
                'label' => __('onboarding.checklist.items.first_client'),
                'done' => $hasClient,
                'href' => route('pme.clients.index'),
            ],
            [
                'key' => 'first_invoice',
                'label' => __('onboarding.checklist.items.first_invoice'),
                'done' => $hasInvoice,
                'href' => route('pme.invoices.create'),
            ],
        ];
    }

    #[Computed]
    public function done(): int
    {
        return collect($this->items)->where('done', true)->count();
    }

    #[Computed]
    public function total(): int
    {
        return count($this->items);
    }

    #[Computed]
    public function isVisible(): bool
    {
        if (! $this->company) {
            return false;
        }
        if (Auth::user()?->onboarding_checklist_dismissed_at) {
            return false;
        }

        return $this->done < $this->total;
    }

    public function dismiss(): void
    {
        Auth::user()?->update(['onboarding_checklist_dismissed_at' => now()]);
    }
}; ?>

<div>
    @if ($this->isVisible)
        <section class="rounded-2xl border border-[#024D4D]/10 bg-white p-6 shadow-soft" data-test="onboarding-checklist">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-primary">
                        {{ __('onboarding.checklist.heading') }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('onboarding.checklist.progress', ['done' => $this->done, 'total' => $this->total]) }}
                    </p>
                </div>

                <button
                    wire:click="dismiss"
                    type="button"
                    class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="{{ __('onboarding.checklist.dismiss') }}"
                    data-test="onboarding-checklist-dismiss"
                >
                    <flux:icon name="x-mark" class="size-4" />
                </button>
            </div>

            <ul role="list" class="mt-4 space-y-2">
                @foreach ($this->items as $item)
                    <li class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 {{ $item['done'] ? 'bg-mist/40' : 'bg-white' }}">
                        @if ($item['done'])
                            <flux:icon name="check-circle" class="size-5 shrink-0 text-accent" />
                            <span class="text-sm font-medium text-slate-600 line-through decoration-slate-300">
                                {{ $item['label'] }}
                            </span>
                        @else
                            <span class="size-5 shrink-0 rounded-full border-2 border-slate-300" aria-hidden="true"></span>
                            @if ($item['href'])
                                <a href="{{ $item['href'] }}" wire:navigate class="flex flex-1 items-center justify-between text-sm font-medium text-ink hover:text-primary">
                                    <span>{{ $item['label'] }}</span>
                                    <flux:icon name="arrow-right" class="size-4 text-slate-400" />
                                </a>
                            @else
                                <span class="text-sm font-medium text-ink">{{ $item['label'] }}</span>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 text-xs leading-relaxed text-slate-500">
                {{ __('onboarding.checklist.tip') }}
            </p>
        </section>
    @endif
</div>
