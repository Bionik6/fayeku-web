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
 * Crée les 5 comptes nommés et les 3 sociétés autour desquels gravite la
 * démo (cabinet Ndiaye + 2 PME). Tout le monde se connecte avec « password ».
 *
 * Les comptables se connectent par email (/accountant/login), les PME par
 * téléphone (/sme/login). Les colonnes `phone_verified_at` et
 * `email_verified_at` ne sont pas mass-assignables : on les pose via
 * forceFill() pour garantir que les comptes seedés peuvent se connecter
 * sans passer par l'OTP / le lien d'activation.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            [$cabinet, $aminata] = $this->createCabinetNdiaye();
            [$diopServices, $moussa] = $this->createDiopServices();
            [$sowBtp, $ibrahima] = $this->createSowBtp();

            $this->linkCabinetToSme($cabinet, $diopServices, monthsAgo: 5);
            $this->linkCabinetToSme($cabinet, $sowBtp, monthsAgo: 2);

            // Plans payants pour les deux PME (utiles pour les tests
            // commission/abonnement).
            $this->subscribe($diopServices, plan: 'essentiel', amount: 20_000, invitedByFirmId: $cabinet->id);
            $this->subscribe($sowBtp, plan: 'basique', amount: 10_000, invitedByFirmId: $cabinet->id);

            // Marqueurs explicites (silence le linter — les variables sont
            // récupérées pour la lisibilité et serviront aux seeders suivants
            // via DB lookups par phone/email).
            unset($aminata, $moussa, $ibrahima);
        });
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function createCabinetNdiaye(): array
    {
        $owner = $this->createUser([
            'first_name' => 'Aminata',
            'last_name' => 'Ndiaye',
            'phone' => '+221774457632',
            'email' => 'aminata@cabinet-ndiaye.test',
            'profile_type' => 'accountant_firm',
        ]);

        $admin = $this->createUser([
            'first_name' => 'Fatou',
            'last_name' => 'Sarr',
            'phone' => '+221774457634',
            'email' => 'fatou@cabinet-ndiaye.test',
            'profile_type' => 'accountant_firm',
        ]);

        $cabinet = Company::create([
            'name' => 'Cabinet Ndiaye Conseil',
            'type' => 'accountant_firm',
            'plan' => 'gold',
            'country_code' => 'SN',
            'phone' => '+221338219900',
            'email' => 'contact@cabinet-ndiaye.test',
            'address' => '25 Rue Carnot, Plateau',
            'city' => 'Dakar',
            'ninea' => 'SN20240199',
            'rccm' => 'SN-DKR-2024-B-19900',
            'invite_code' => Str::upper(Str::random(6)),
        ]);

        $cabinet->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);
        $cabinet->users()->attach($admin->id, ['role' => CompanyRole::Admin->value]);

        return [$cabinet, $owner];
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function createDiopServices(): array
    {
        $owner = $this->createUser([
            'first_name' => 'Moussa',
            'last_name' => 'Diop',
            'phone' => '+221774457633',
            'email' => 'moussa@diop-services.test',
            'profile_type' => 'sme',
        ]);

        $member = $this->createUser([
            'first_name' => 'Awa',
            'last_name' => 'Ba',
            'phone' => '+221774457635',
            'email' => 'awa@diop-services.test',
            'profile_type' => 'sme',
        ]);

        $company = Company::create([
            'name' => 'Diop Services SARL',
            'type' => 'sme',
            'plan' => 'essentiel',
            'country_code' => 'SN',
            'phone' => '+221338219901',
            'email' => 'contact@diop-services.test',
            'address' => '15 Avenue Bourguiba',
            'city' => 'Dakar',
            'sector' => 'Services numériques',
            'ninea' => 'SN20240001',
            'rccm' => 'SN-DKR-2024-B-00001',
            'setup_completed_at' => now(),
        ]);

        $company->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);
        $company->users()->attach($member->id, ['role' => CompanyRole::Member->value]);

        return [$company, $owner];
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function createSowBtp(): array
    {
        $owner = $this->createUser([
            'first_name' => 'Ibrahima',
            'last_name' => 'Sow',
            'phone' => '+221774457636',
            'email' => 'ibrahima@sow-btp.test',
            'profile_type' => 'sme',
        ]);

        $company = Company::create([
            'name' => 'Sow BTP SARL',
            'type' => 'sme',
            'plan' => 'basique',
            'country_code' => 'SN',
            'phone' => '+221338219902',
            'email' => 'contact@sow-btp.test',
            'address' => '8 Rue 12, Liberté 5',
            'city' => 'Dakar',
            'sector' => 'BTP',
            'ninea' => 'SN20240002',
            'rccm' => 'SN-DKR-2024-B-00002',
            'setup_completed_at' => now(),
        ]);

        $company->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);

        return [$company, $owner];
    }

    /**
     * @param  array{first_name: string, last_name: string, phone: string, email?: string, profile_type: string}  $attributes
     */
    private function createUser(array $attributes): User
    {
        $user = User::create([
            'first_name' => $attributes['first_name'],
            'last_name' => $attributes['last_name'],
            'phone' => $attributes['phone'],
            'email' => $attributes['email'] ?? null,
            'password' => 'password',
            'profile_type' => $attributes['profile_type'],
            'country_code' => 'SN',
            'is_active' => true,
        ]);

        // phone_verified_at et email_verified_at ne sont pas dans $fillable —
        // on doit les poser explicitement via forceFill, sinon les comptes
        // seedés sont systématiquement renvoyés vers l'OTP au login.
        $user->forceFill([
            'phone_verified_at' => now(),
            'email_verified_at' => $attributes['email'] ?? null ? now() : null,
        ])->save();

        return $user;
    }

    private function linkCabinetToSme(Company $cabinet, Company $sme, int $monthsAgo): void
    {
        AccountantCompany::create([
            'accountant_firm_id' => $cabinet->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths($monthsAgo),
        ]);
    }

    private function subscribe(Company $sme, string $plan, int $amount, string $invitedByFirmId): void
    {
        Subscription::create([
            'company_id' => $sme->id,
            'plan_slug' => $plan,
            'price_paid' => $amount,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->startOfMonth()->addMonth(),
            'cancelled_at' => null,
            'invited_by_firm_id' => $invitedByFirmId,
        ]);
    }
}
