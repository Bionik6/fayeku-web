<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Auth\Company;
use App\Enums\Shared\QuotaType;
use App\Exceptions\Shared\QuotaExceededException;

class QuotaService
{
    public function authorize(Company $company, string|QuotaType $type, int $amount = 1): void
    {
        $t = $type instanceof QuotaType ? $type->value : $type;
        if ($this->isUnlimited($company, $t)) {
            return;
        }
        if ($this->available($company, $t) < $amount) {
            throw new QuotaExceededException($t);
        }
    }

    public function consume(Company $company, string|QuotaType $type, int $amount = 1): void
    {
        $t = $type instanceof QuotaType ? $type->value : $type;

        if (! Schema::hasTable('quota_usage')) {
            return;
        }

        $period = $this->isMonthly($t) ? now()->startOfMonth()->toDateString() : null;

        DB::table('quota_usage')->upsert(
            ['id' => (string) Str::ulid(), 'company_id' => $company->id,
                'quota_type' => $t, 'period_start' => $period,
                'used' => $amount, 'created_at' => now(), 'updated_at' => now()],
            ['company_id', 'quota_type', 'period_start'],
            ['used' => DB::raw("quota_usage.used + {$amount}"), 'updated_at' => now()]
        );
    }

    public function available(Company $company, string|QuotaType $type): int
    {
        $t = $type instanceof QuotaType ? $type->value : $type;
        $limit = $this->planLimit($company, $t);
        $used = $this->currentUsage($company, $t);
        $addons = $this->addonCredits($company, $t);

        return ($limit - $used) + $addons;
    }

    public function isUnlimited(Company $company, string|QuotaType $type): bool
    {
        $t = $type instanceof QuotaType ? $type->value : $type;

        return $this->planLimit($company, $t) === -1;
    }

    private function planLimit(Company $company, string $t): int
    {
        if (! Schema::hasTable('plan_definitions')) {
            return $this->defaultPlanLimit($company, $t);
        }

        $plan = DB::table('plan_definitions')
            ->where('slug', $company->subscription?->plan_slug)
            ->first();
        if (! $plan) {
            return $this->defaultPlanLimit($company, $t);
        }

        return match ($t) {
            'reminders' => $plan->reminders_per_month,
            'users' => $plan->max_users,
            'clients' => $plan->max_clients,
            'storage_mb' => $plan->max_storage_mb,
            default => 0,
        };
    }

    private function currentUsage(Company $company, string $t): int
    {
        if (! Schema::hasTable('quota_usage')) {
            return $t === QuotaType::Reminders->value && Schema::hasTable('reminders') && Schema::hasTable('invoices')
                ? (int) DB::table('reminders')
                    ->join('invoices', 'invoices.id', '=', 'reminders.invoice_id')
                    ->where('invoices.company_id', $company->id)
                    ->whereMonth('reminders.created_at', now()->month)
                    ->whereYear('reminders.created_at', now()->year)
                    ->count()
                : 0;
        }

        $period = $this->isMonthly($t) ? now()->startOfMonth()->toDateString() : null;
        $q = DB::table('quota_usage')
            ->where('company_id', $company->id)->where('quota_type', $t);
        $period ? $q->where('period_start', $period) : $q->whereNull('period_start');

        return (int) $q->value('used');
    }

    private function addonCredits(Company $company, string $t): int
    {
        if (! Schema::hasTable('addon_purchases')) {
            return 0;
        }

        return (int) DB::table('addon_purchases')
            ->where('company_id', $company->id)->where('addon_type', $t)
            ->where('credits_remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('credits_remaining');
    }

    private function isMonthly(string $t): bool
    {
        return $t === QuotaType::Reminders->value;
    }

    private function defaultPlanLimit(Company $company, string $t): int
    {
        $plan = strtolower($company->subscription?->plan_slug ?? $company->plan ?? 'basique');

        return match ($t) {
            QuotaType::Reminders->value => match ($plan) {
                'basique' => 20,
                'essentiel', 'entreprise' => -1,
                default => 0,
            },
            QuotaType::Users->value => match ($plan) {
                'basique' => 2,
                'essentiel', 'entreprise' => -1,
                default => 0,
            },
            QuotaType::Clients->value => -1,
            QuotaType::StorageMb->value => 0,
            default => 0,
        };
    }
}
