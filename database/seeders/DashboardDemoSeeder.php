<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $firm = $this->createCabinet();
            $smes = $this->createPortfolio($firm);
            $this->createCommissions($firm, $smes);
        });
    }

    private function createCabinet(): Company
    {
        $owner = User::create([
            'first_name' => 'Ousmane',
            'last_name' => 'Diallo',
            'phone' => '+221774458001',
            'password' => 'passer1234',
            'profile_type' => 'accountant_firm',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $collaborator = User::create([
            'first_name' => 'Mariama',
            'last_name' => 'Diallo',
            'phone' => '+221774458002',
            'password' => 'passer1234',
            'profile_type' => 'accountant_firm',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $firm = Company::create([
            'name' => 'Cabinet Diallo & Associés',
            'type' => 'accountant_firm',
            'plan' => 'gold',
            'country_code' => 'SN',
            'phone' => '+221338220100',
        ]);

        $firm->users()->attach($owner->id, ['role' => 'owner']);
        $firm->users()->attach($collaborator->id, ['role' => 'admin']);

        return $firm;
    }

    /** @return array<string, Company> */
    private function createPortfolio(Company $firm): array
    {
        $smes = [];

        // ─── 2 Critiques (overdue > 60 jours) ──────────────────────────────

        $kaneImport = $this->createSme($firm, 'Kane Import SARL', 'essentiel', '+221338220101');
        $this->createKaneImportInvoices($kaneImport);
        $smes['kane_import'] = $kaneImport;

        $thiesIndustries = $this->createSme($firm, 'Thiès Industries SA', 'essentiel', '+221338220102');
        $this->createThiesIndustriesInvoices($thiesIndustries);
        $smes['thies_industries'] = $thiesIndustries;

        // ─── 4 À surveiller (inactif ou overdue < 60j) ──────────────────────

        $sowBtp = $this->createSme($firm, 'Sow BTP SARL', 'essentiel', '+221338220103');
        $this->createSowBtpInvoices($sowBtp);
        $smes['sow_btp'] = $sowBtp;

        $mbayeTransport = $this->createSme($firm, 'Mbaye Transport', 'basique', '+221338220104');
        $this->createWatchInvoices($mbayeTransport, overdueAgoDays: 40);
        $smes['mbaye_transport'] = $mbayeTransport;

        $couryCommerce = $this->createSme($firm, 'Coury Commerce', 'basique', '+221338220105');
        $this->createInactiveInvoices($couryCommerce, lastInvoiceAgoDays: 35);
        $smes['coury_commerce'] = $couryCommerce;

        $diyeConsulting = $this->createSme($firm, 'Diye Consulting', 'essentiel', '+221338220106');
        $this->createWatchInvoices($diyeConsulting, overdueAgoDays: 25);
        $smes['diye_consulting'] = $diyeConsulting;

        // ─── 12 À jour ───────────────────────────────────────────────────────

        $diopServices = $this->createSme($firm, 'Diop Services SARL', 'basique', '+221338220107');
        $this->createHealthyInvoices($diopServices, count: 3, totalPerInvoice: 350_000);
        $smes['diop_services'] = $diopServices;

        $dakarPharma = $this->createSme($firm, 'Dakar Pharma', 'essentiel', '+221338220108');
        $this->createHealthyInvoices($dakarPharma, count: 2, totalPerInvoice: 480_000);
        $this->createPartnerInvitation($firm, $dakarPharma, 'Dakar Pharma', '+221701890001');
        $smes['dakar_pharma'] = $dakarPharma;

        $coulibaly = $this->createSme($firm, 'Coulibaly Tech', 'essentiel', '+221338220109');
        $this->createHealthyInvoices($coulibaly, count: 4, totalPerInvoice: 600_000);
        $smes['coulibaly_tech'] = $coulibaly;

        $ndiayeCommerce = $this->createSme($firm, 'Ndiaye Commerce', 'basique', '+221338220110');
        $this->createHealthyInvoices($ndiayeCommerce, count: 2, totalPerInvoice: 275_000);
        $smes['ndiaye_commerce'] = $ndiayeCommerce;

        $syConsulting = $this->createSme($firm, 'Sy Consulting', 'essentiel', '+221338220111');
        $this->createHealthyInvoices($syConsulting, count: 5, totalPerInvoice: 420_000);
        $smes['sy_consulting'] = $syConsulting;

        $traoreAgri = $this->createSme($firm, 'Traoré Agriculture', 'basique', '+221338220112');
        $this->createHealthyInvoices($traoreAgri, count: 3, totalPerInvoice: 190_000);
        $smes['traore_agriculture'] = $traoreAgri;

        $lyFashion = $this->createSme($firm, 'Ly Fashion', 'basique', '+221338220113');
        $this->createHealthyInvoices($lyFashion, count: 2, totalPerInvoice: 315_000);
        $smes['ly_fashion'] = $lyFashion;

        $baIndustries = $this->createSme($firm, 'Bâ Industries', 'essentiel', '+221338220114');
        $this->createHealthyInvoices($baIndustries, count: 6, totalPerInvoice: 720_000);
        $smes['ba_industries'] = $baIndustries;

        $koneServices = $this->createSme($firm, 'Koné Services', 'basique', '+221338220115');
        $this->createHealthyInvoices($koneServices, count: 3, totalPerInvoice: 250_000);
        $smes['kone_services'] = $koneServices;

        $toureImmo = $this->createSme($firm, 'Touré Immobilier', 'essentiel', '+221338220116');
        $this->createHealthyInvoices($toureImmo, count: 4, totalPerInvoice: 950_000);
        $smes['toure_immobilier'] = $toureImmo;

        $cisseAssocies = $this->createSme($firm, 'Cissé & Associés', 'essentiel', '+221338220117');
        $this->createHealthyInvoices($cisseAssocies, count: 3, totalPerInvoice: 550_000);
        $smes['cisse_associes'] = $cisseAssocies;

        $fallDigital = $this->createSme($firm, 'Fall Digital', 'essentiel', '+221338220118');
        $this->createHealthyInvoices($fallDigital, count: 5, totalPerInvoice: 380_000);
        $smes['fall_digital'] = $fallDigital;

        return $smes;
    }

    private function createSme(Company $firm, string $name, string $plan, string $phone): Company
    {
        $owner = User::create([
            'first_name' => explode(' ', $name)[0],
            'last_name' => 'Owner',
            'phone' => $phone,
            'password' => 'passer1234',
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $sme = Company::create([
            'name' => $name,
            'type' => 'sme',
            'plan' => $plan,
            'country_code' => 'SN',
            'phone' => $phone,
        ]);

        $sme->users()->attach($owner->id, ['role' => 'owner']);

        Subscription::create([
            'company_id' => $sme->id,
            'plan_slug' => $plan,
            'price_paid' => $plan === 'essentiel' ? 20_000 : 10_000,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->startOfMonth()->addMonth(),
            'invited_by_firm_id' => $firm->id,
        ]);

        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths(3),
        ]);

        return $sme;
    }

    /**
     * Kane Import SARL — critique : FAC-089, 850 000 F, J+62, 7 impayés,
     * ~4 200 000 F en attente, ~72 % de recouvrement.
     */
    private function createKaneImportInvoices(Company $sme): void
    {
        foreach (range(1, 12) as $i) {
            $this->createInvoice($sme, [
                'reference' => sprintf('FAC-%03d', $i),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(6)->addWeeks($i),
                'due_at' => now()->subMonths(5)->addWeeks($i),
                'total' => 900_000,
                'amount_paid' => 900_000,
            ]);
        }

        foreach (range(83, 88) as $i) {
            $this->createInvoice($sme, [
                'reference' => sprintf('FAC-%03d', $i),
                'status' => InvoiceStatus::Overdue,
                'issued_at' => now()->subDays(75),
                'due_at' => now()->subDays(65),
                'total' => 558_333,
                'amount_paid' => 0,
            ]);
        }

        $this->createInvoice($sme, [
            'reference' => 'FAC-089',
            'status' => InvoiceStatus::Overdue,
            'issued_at' => now()->subDays(72),
            'due_at' => now()->subDays(62),
            'total' => 850_000,
            'amount_paid' => 0,
        ]);
    }

    /**
     * Thiès Industries SA — critique : overdue depuis 65 jours.
     */
    private function createThiesIndustriesInvoices(Company $sme): void
    {
        foreach (range(1, 8) as $i) {
            $this->createInvoice($sme, [
                'reference' => sprintf('TI-%03d', $i),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(5)->addWeeks($i),
                'due_at' => now()->subMonths(4)->addWeeks($i),
                'total' => 750_000,
                'amount_paid' => 750_000,
            ]);
        }

        foreach (range(9, 13) as $i) {
            $this->createInvoice($sme, [
                'reference' => sprintf('TI-%03d', $i),
                'status' => InvoiceStatus::Overdue,
                'issued_at' => now()->subDays(78),
                'due_at' => now()->subDays(65),
                'total' => 620_000,
                'amount_paid' => 0,
            ]);
        }
    }

    /**
     * Sow BTP SARL — à surveiller : inactif 18 j, 3 impayés,
     * 1 450 000 F en attente, ~81 % de recouvrement.
     */
    private function createSowBtpInvoices(Company $sme): void
    {
        foreach (range(1, 7) as $i) {
            $this->createInvoice($sme, [
                'reference' => sprintf('SB-%03d', $i),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(4)->addWeeks($i),
                'due_at' => now()->subMonths(3)->addWeeks($i),
                'total' => 878_571,
                'amount_paid' => 878_571,
            ]);
        }

        $overdueAmounts = [500_000, 450_000, 500_000];
        foreach ($overdueAmounts as $j => $amount) {
            $this->createInvoice($sme, [
                'reference' => sprintf('SB-%03d', 8 + $j),
                'status' => InvoiceStatus::Overdue,
                'issued_at' => now()->subDays(28 - ($j * 3)),
                'due_at' => now()->subDays(18 - ($j * 3)),
                'total' => $amount,
                'amount_paid' => 0,
            ]);
        }
    }

    private function createWatchInvoices(Company $sme, int $overdueAgoDays): void
    {
        foreach (range(1, 4) as $i) {
            $this->createInvoice($sme, [
                'reference' => sprintf('INV-%03d', $i),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(3)->addWeeks($i),
                'due_at' => now()->subMonths(2)->addWeeks($i),
                'total' => 400_000,
                'amount_paid' => 400_000,
            ]);
        }

        $this->createInvoice($sme, [
            'reference' => 'INV-005',
            'status' => InvoiceStatus::Overdue,
            'issued_at' => now()->subDays($overdueAgoDays + 15),
            'due_at' => now()->subDays($overdueAgoDays),
            'total' => 380_000,
            'amount_paid' => 0,
        ]);
    }

    private function createInactiveInvoices(Company $sme, int $lastInvoiceAgoDays): void
    {
        foreach (range(1, 5) as $i) {
            $this->createInvoice($sme, [
                'reference' => sprintf('INV-%03d', $i),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subDays($lastInvoiceAgoDays + (($i - 1) * 15)),
                'due_at' => now()->subDays($lastInvoiceAgoDays - 15 + (($i - 1) * 15)),
                'total' => 320_000,
                'amount_paid' => 320_000,
            ]);
        }
    }

    private function createHealthyInvoices(Company $sme, int $count, int $totalPerInvoice): void
    {
        foreach (range(1, $count) as $i) {
            $isLatest = $i === $count;
            $this->createInvoice($sme, [
                'reference' => sprintf('INV-%03d', $i),
                'status' => InvoiceStatus::Paid,
                'issued_at' => $isLatest ? now()->subDays(rand(1, 7)) : now()->subMonths(4)->addWeeks($i),
                'due_at' => $isLatest ? now()->addDays(23) : now()->subMonths(3)->addWeeks($i),
                'total' => $totalPerInvoice,
                'amount_paid' => $totalPerInvoice,
            ]);
        }
    }

    private function createPartnerInvitation(Company $firm, Company $sme, string $name, string $phone): void
    {
        PartnerInvitation::create([
            'accountant_firm_id' => $firm->id,
            'token' => Str::uuid(),
            'invitee_phone' => $phone,
            'invitee_name' => $name,
            'recommended_plan' => 'essentiel',
            'status' => 'accepted',
            'expires_at' => now()->addDays(28),
            'accepted_at' => now()->subDays(2),
            'sme_company_id' => $sme->id,
        ]);
    }

    /**
     * Commissions du mois courant — total exactement 187 500 F.
     *
     * @param  array<string, Company>  $smes
     */
    private function createCommissions(Company $firm, array $smes): void
    {
        /** @var array<string, int> */
        $amounts = [
            // 5 × 15 000 F = 75 000 F
            'kane_import'        => 15_000,
            'thies_industries'   => 15_000,
            'sow_btp'            => 15_000,
            'coulibaly_tech'     => 15_000,
            'ba_industries'      => 15_000,
            // 8 × 10 000 F = 80 000 F
            'mbaye_transport'    => 10_000,
            'coury_commerce'     => 10_000,
            'diye_consulting'    => 10_000,
            'diop_services'      => 10_000,
            'dakar_pharma'       => 10_000,
            'ndiaye_commerce'    => 10_000,
            'sy_consulting'      => 10_000,
            'traore_agriculture' => 10_000,
            // 5 × 6 500 F = 32 500 F
            'toure_immobilier'   =>  6_500,
            'ly_fashion'         =>  6_500,
            'kone_services'      =>  6_500,
            'cisse_associes'     =>  6_500,
            'fall_digital'       =>  6_500,
        ];
        // Total : 75 000 + 80 000 + 32 500 = 187 500 F ✓

        foreach ($amounts as $key => $amount) {
            if (! isset($smes[$key])) {
                continue;
            }

            Commission::create([
                'accountant_firm_id' => $firm->id,
                'sme_company_id'     => $smes[$key]->id,
                'amount'             => $amount,
                'period_month'       => now()->startOfMonth(),
                'status'             => 'pending',
            ]);

            // Historique sur 2 mois
            foreach ([1, 2] as $monthsAgo) {
                Commission::create([
                    'accountant_firm_id' => $firm->id,
                    'sme_company_id'     => $smes[$key]->id,
                    'amount'             => $amount,
                    'period_month'       => now()->subMonths($monthsAgo)->startOfMonth(),
                    'status'             => 'paid',
                    'paid_at'            => now()->subMonths($monthsAgo)->endOfMonth(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInvoice(Company $sme, array $attributes): Invoice
    {
        return Invoice::unguarded(fn () => Invoice::create(array_merge([
            'company_id' => $sme->id,
            'subtotal'   => $attributes['total'] ?? 0,
            'tax_amount' => 0,
        ], $attributes)));
    }
}
