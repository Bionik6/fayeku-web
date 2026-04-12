<?php

namespace App\Livewire\Sidebar;

use App\Models\Auth\Company;
use App\Services\Compta\AlertService;
use Livewire\Attributes\On;
use Livewire\Component;

class AlertsBadge extends Component
{
    public int $count = 0;

    public function mount(AlertService $alerts): void
    {
        $this->count = $this->resolveCount($alerts);
    }

    #[On('alerts-updated')]
    public function refreshCount(AlertService $alerts): void
    {
        $this->count = $this->resolveCount($alerts);
    }

    public function render()
    {
        return view('livewire.sidebar.alerts-badge');
    }

    private function resolveCount(AlertService $alerts): int
    {
        $user = auth()->user();

        if (! $user) {
            return 0;
        }

        $firm = $user->accountantFirm();

        if (! $firm instanceof Company) {
            return 0;
        }

        return $alerts->count($firm, $user);
    }
}
