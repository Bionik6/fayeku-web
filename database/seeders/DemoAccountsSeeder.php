<?php

namespace Database\Seeders;

use App\Enums\Auth\CompanyRole;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Shared\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $accountantUser = User::query()->create([
                'first_name' => 'Aminata',
                'last_name' => 'Ndiaye',
                'phone' => '+221774457632',
                'password' => 'passer1234',
                'profile_type' => 'accountant_firm',
                'country_code' => 'SN',
                'is_active' => true,
                'phone_verified_at' => now(),
            ]);

            $accountantCompany = Company::query()->create([
                'name' => 'Cabinet Ndiaye Conseil',
                'type' => 'accountant_firm',
                'plan' => 'basique',
                'country_code' => 'SN',
                'phone' => '+221338219900',
            ]);

            $accountantCompany->users()->attach($accountantUser->id, ['role' => CompanyRole::Owner->value]);

            $pmeUser = User::query()->create([
                'first_name' => 'Moussa',
                'last_name' => 'Diop',
                'phone' => '+221774457633',
                'password' => 'passer1234',
                'profile_type' => 'sme',
                'country_code' => 'SN',
                'is_active' => true,
                'phone_verified_at' => now(),
            ]);

            $pmeCompany = Company::query()->create([
                'name' => 'Diop Services SARL',
                'type' => 'sme',
                'plan' => 'essentiel',
                'country_code' => 'SN',
                'phone' => '+221338219901',
            ]);

            $pmeCompany->users()->attach($pmeUser->id, ['role' => CompanyRole::Owner->value]);

            $assistantUser = User::query()->create([
                'first_name' => 'Fatou',
                'last_name' => 'Sarr',
                'phone' => '+221774457634',
                'password' => 'passer1234',
                'profile_type' => 'accountant_firm',
                'country_code' => 'SN',
                'is_active' => true,
                'phone_verified_at' => now(),
            ]);

            $accountantCompany->users()->attach($assistantUser->id, ['role' => CompanyRole::Admin->value]);

            $pmeTeammate = User::query()->create([
                'first_name' => 'Awa',
                'last_name' => 'Ba',
                'phone' => '+221774457635',
                'password' => 'passer1234',
                'profile_type' => 'sme',
                'country_code' => 'SN',
                'is_active' => true,
                'phone_verified_at' => now(),
            ]);

            $pmeCompany->users()->attach($pmeTeammate->id, ['role' => CompanyRole::Member->value]);

            $secondSmeCompany = Company::query()->create([
                'name' => 'Sow BTP SARL',
                'type' => 'sme',
                'plan' => 'basique',
                'country_code' => 'SN',
                'phone' => '+221338219902',
            ]);

            $secondSmeOwner = User::query()->create([
                'first_name' => 'Ibrahima',
                'last_name' => 'Sow',
                'phone' => '+221774457636',
                'password' => 'passer1234',
                'profile_type' => 'sme',
                'country_code' => 'SN',
                'is_active' => true,
                'phone_verified_at' => now(),
            ]);

            $secondSmeCompany->users()->attach($secondSmeOwner->id, ['role' => CompanyRole::Owner->value]);

            Subscription::query()->create([
                'company_id' => $pmeCompany->id,
                'plan_slug' => 'essentiel',
                'price_paid' => 20000,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->startOfMonth()->addMonth(),
                'cancelled_at' => null,
                'invited_by_firm_id' => $accountantCompany->id,
            ]);

            Subscription::query()->create([
                'company_id' => $secondSmeCompany->id,
                'plan_slug' => 'basique',
                'price_paid' => 10000,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->startOfMonth()->addMonth(),
                'cancelled_at' => null,
                'invited_by_firm_id' => $accountantCompany->id,
            ]);

            AccountantCompany::query()->create([
                'accountant_firm_id' => $accountantCompany->id,
                'sme_company_id' => $pmeCompany->id,
                'started_at' => now()->subDays(45),
                'ended_at' => null,
                'ended_reason' => null,
            ]);

            AccountantCompany::query()->create([
                'accountant_firm_id' => $accountantCompany->id,
                'sme_company_id' => $secondSmeCompany->id,
                'started_at' => now()->subDays(20),
                'ended_at' => null,
                'ended_reason' => null,
            ]);
        });
    }
}
