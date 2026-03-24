<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Shared\Models\User;

class PmeDashboardSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::query()->create([
                'first_name' => 'Oumar',
                'last_name' => 'Faye',
                'phone' => '+221770000001',
                'password' => 'passer1234',
                'profile_type' => 'sme',
                'country_code' => 'SN',
                'is_active' => true,
                'phone_verified_at' => now(),
            ]);

            $company = Company::query()->create([
                'name' => 'Faye & Associés SARL',
                'type' => 'sme',
                'plan' => 'essentiel',
                'country_code' => 'SN',
                'phone' => '+221338200001',
                'email' => 'contact@faye-associes.sn',
                'address' => '15 Avenue Bourguiba',
                'city' => 'Dakar',
                'ninea' => 'SN20240001',
                'rccm' => 'SN-DKR-2024-B-00001',
            ]);

            $company->users()->attach($user->id, ['role' => 'owner']);

            Subscription::query()->create([
                'company_id' => $company->id,
                'plan_slug' => 'essentiel',
                'price_paid' => 20000,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->startOfMonth()->addMonth(),
                'cancelled_at' => null,
                'invited_by_firm_id' => null,
            ]);
        });
    }
}
