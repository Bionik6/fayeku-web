<?php

use App\Models\Auth\Company;
use App\Models\Shared\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?User $user = null;

    public ?Company $company = null;

    public function mount(): void
    {
        // On initial render, reuse the request's auth user instance so we
        // benefit from the per-instance smeCompany() cache shared with the
        // layout (1 query per page load — see SmeCompanyQueryTest).
        $authUser = Auth::user();
        $this->user = $authUser;
        $this->company = $authUser?->smeCompany();
    }

    /**
     * Listener fired by the settings page after the user updates their
     * personal info (Mon Profil) — re-reads the User model so the badge
     * shows the new first/last name without a page reload.
     */
    #[On('account-updated')]
    public function onAccountUpdated(): void
    {
        $this->refreshFromDatabase();
    }

    /**
     * Listener fired by the settings page after the user updates their
     * company info (Mon Entreprise / signature / logo) — re-reads the
     * Company model so the badge shows the new company name.
     */
    #[On('company-updated')]
    public function onCompanyUpdated(): void
    {
        $this->refreshFromDatabase();
    }

    #[Computed]
    public function companyName(): string
    {
        return trim((string) ($this->company?->name ?? '')) !== ''
            ? $this->company->name
            : __('Mon entreprise');
    }

    /**
     * Re-fetch the user and company from the database to bypass the
     * per-instance smeCompany() cache. Only called by event listeners,
     * never on initial mount, to keep the page-load query count flat.
     */
    private function refreshFromDatabase(): void
    {
        $authUser = Auth::user();

        if (! $authUser) {
            $this->user = null;
            $this->company = null;

            return;
        }

        $this->user = $authUser->fresh();
        $this->company = $this->user?->smeCompany();
    }
}; ?>

<div class="flex items-center gap-3">
    @if ($user)
        <div class="flex size-9 items-center justify-center rounded-xl bg-mist text-xs font-bold text-primary">
            {{ $user->initials() }}
        </div>
        <div class="hidden min-w-0 sm:block">
            <p class="truncate text-sm font-semibold text-ink">{{ $user->full_name }}</p>
            <p class="truncate text-xs text-slate-500">{{ $this->companyName }}</p>
        </div>
    @endif
</div>
