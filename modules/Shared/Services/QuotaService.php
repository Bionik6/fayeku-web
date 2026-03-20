<?php

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\Company;
use Modules\Shared\Enums\QuotaType;
use Modules\Shared\Exceptions\QuotaExceededException;

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
        $plan = DB::table('plan_definitions')
            ->where('slug', $company->subscription?->plan_slug)
            ->first();
        if (! $plan) {
            return 0;
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
        $period = $this->isMonthly($t) ? now()->startOfMonth()->toDateString() : null;
        $q = DB::table('quota_usage')
            ->where('company_id', $company->id)->where('quota_type', $t);
        $period ? $q->where('period_start', $period) : $q->whereNull('period_start');

        return (int) $q->value('used');
    }

    private function addonCredits(Company $company, string $t): int
    {
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
}
