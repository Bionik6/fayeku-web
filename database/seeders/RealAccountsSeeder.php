<?php

namespace Database\Seeders;

use App\Enums\Auth\CompanyRole;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Shared\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comptes réels pour tester les flows d'auth (magic link, reset password,
 * login). Mot de passe : `password`. Tous les comptes ont `email_verified_at`
 * marqué — le login fonctionne directement, sans passer par /auth/verify-email.
 *
 * ┌── Cabinet : Cabinet Bionik ─────────────────────────────────────────────┐
 * │ Owner   bionik6@gmail.com                                              │
 * └────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── Cabinet : Cabinet Ibrahima Ciss ──────────────────────────────────────┐
 * │ Owner   iamibrahimaciss@gmail.com                                      │
 * └────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Iciss Dev SARL (liée à Cabinet Bionik) ─────────────────────────┐
 * │ Owner   icissdev@gmail.com                                             │
 * └────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Callme Ibou Services (PME autonome) ────────────────────────────┐
 * │ Owner   callmeibou@gmail.com                                           │
 * └────────────────────────────────────────────────────────────────────────┘
 *
 * Pour tester l'inscription : utiliser un alias gmail (ex.
 * icissdev+test1@gmail.com) ou supprimer une de ces lignes en base.
 */
class RealAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $cabinetBionik = $this->createCabinet(
                ownerEmail: 'bionik6@gmail.com',
                ownerFirstName: 'Bionik',
                ownerLastName: 'Six',
                ownerPhone: '+221781000001',
                cabinetName: 'Cabinet Bionik',
                cabinetEmail: 'contact@bionik.test',
                cabinetPhone: '+221338100001',
            );

            $this->createCabinet(
                ownerEmail: 'iamibrahimaciss@gmail.com',
                ownerFirstName: 'Ibrahima',
                ownerLastName: 'Ciss',
                ownerPhone: '+221781000002',
                cabinetName: 'Cabinet Ibrahima Ciss',
                cabinetEmail: 'contact@ibrahima-ciss.test',
                cabinetPhone: '+221338100002',
            );

            // PME liée au Cabinet Bionik (le cabinet la verra dans son dashboard).
            $icissDev = $this->createSme(
                ownerEmail: 'icissdev@gmail.com',
                ownerFirstName: 'Iciss',
                ownerLastName: 'Dev',
                ownerPhone: '+221781000003',
                companyName: 'Iciss Dev SARL',
                companyEmail: 'contact@iciss-dev.test',
                companyPhone: '+221338100003',
                sector: 'Services numériques',
                ninea: 'SN20260101',
                rccm: 'SN-DKR-2026-B-00101',
            );

            AccountantCompany::create([
                'accountant_firm_id' => $cabinetBionik->id,
                'sme_company_id' => $icissDev->id,
                'started_at' => now()->subMonths(2),
            ]);

            // Mettre à jour la subscription pour refléter l'invitation par le cabinet.
            Subscription::where('company_id', $icissDev->id)->update([
                'invited_by_firm_id' => $cabinetBionik->id,
            ]);

            // PME autonome (sans cabinet).
            $this->createSme(
                ownerEmail: 'callmeibou@gmail.com',
                ownerFirstName: 'Ibou',
                ownerLastName: 'Callme',
                ownerPhone: '+221781000004',
                companyName: 'Callme Ibou Services',
                companyEmail: 'contact@callme-ibou.test',
                companyPhone: '+221338100004',
                sector: 'Conseil',
                ninea: 'SN20260102',
                rccm: 'SN-DKR-2026-B-00102',
            );
        });
    }

    private function createCabinet(
        string $ownerEmail,
        string $ownerFirstName,
        string $ownerLastName,
        string $ownerPhone,
        string $cabinetName,
        string $cabinetEmail,
        string $cabinetPhone,
    ): Company {
        $owner = $this->createVerifiedUser(
            email: $ownerEmail,
            firstName: $ownerFirstName,
            lastName: $ownerLastName,
            phone: $ownerPhone,
            profileType: 'accountant_firm',
        );

        $cabinet = Company::create([
            'name' => $cabinetName,
            'type' => 'accountant_firm',
            'plan' => 'gold',
            'country_code' => 'SN',
            'phone' => $cabinetPhone,
            'email' => $cabinetEmail,
            'invite_code' => Str::upper(Str::random(6)),
        ]);

        $cabinet->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);

        return $cabinet;
    }

    private function createSme(
        string $ownerEmail,
        string $ownerFirstName,
        string $ownerLastName,
        string $ownerPhone,
        string $companyName,
        string $companyEmail,
        string $companyPhone,
        string $sector,
        string $ninea,
        string $rccm,
    ): Company {
        $owner = $this->createVerifiedUser(
            email: $ownerEmail,
            firstName: $ownerFirstName,
            lastName: $ownerLastName,
            phone: $ownerPhone,
            profileType: 'sme',
        );

        $company = Company::create([
            'name' => $companyName,
            'type' => 'sme',
            'plan' => 'essentiel',
            'country_code' => 'SN',
            'phone' => $companyPhone,
            'email' => $companyEmail,
            'sector' => $sector,
            'ninea' => $ninea,
            'rccm' => $rccm,
            'setup_completed_at' => now(),
        ]);

        $company->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);

        Subscription::create([
            'company_id' => $company->id,
            'plan_slug' => 'essentiel',
            'price_paid' => 20_000,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->startOfMonth()->addMonth(),
        ]);

        return $company;
    }

    private function createVerifiedUser(
        string $email,
        string $firstName,
        string $lastName,
        string $phone,
        string $profileType,
    ): User {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $email,
            'password' => 'password',
            'profile_type' => $profileType,
            'country_code' => 'SN',
            'is_active' => true,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ])->save();

        return $user;
    }
}
