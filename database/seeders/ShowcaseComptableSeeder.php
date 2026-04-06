<?php

namespace Database\Seeders;

use Database\Factories\Support\SenegalFaker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Compta\Partnership\Models\Commission;
use Modules\Compta\Partnership\Models\CommissionPayment;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Compta\Partnership\Services\CommissionService;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\InvoiceLine;
use Modules\Shared\Models\User;

/**
 * Showcase seed — Comptable profile
 *
 * Credentials : +221776100001 / passer1234
 * Company     : Fiduciaire Atlantique SA (plan Gold, Dakar)
 */
class ShowcaseComptableSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $firm = $this->createCabinet();
            $smes = $this->createPortfolio($firm);
            $this->createCommissions($firm, $smes);
            $this->createCommissionPayments($firm);
            $this->createInvitations($firm, $smes);
        });
    }

    private function createCabinet(): Company
    {
        $owner = User::create([
            'first_name' => 'Ibrahima',
            'last_name' => 'Diagne',
            'phone' => '+221776100001',
            'password' => 'passer1234',
            'profile_type' => 'accountant_firm',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $admin = User::create([
            'first_name' => 'Mame Diarra',
            'last_name' => 'Sène',
            'phone' => '+221776100002',
            'password' => 'passer1234',
            'profile_type' => 'accountant_firm',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $member = User::create([
            'first_name' => 'Ibou',
            'last_name' => 'Diatta',
            'phone' => '+221776100003',
            'password' => 'passer1234',
            'profile_type' => 'accountant_firm',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $firm = Company::create([
            'name' => 'Fiduciaire Atlantique SA',
            'type' => 'accountant_firm',
            'plan' => 'gold',
            'country_code' => 'SN',
            'phone' => '+221338710001',
            'email' => 'contact@fiduciaire-atlantique.sn',
            'address' => '18 Avenue Cheikh Anta Diop, Fann',
            'city' => 'Dakar',
            'ninea' => 'SN20240180',
            'rccm' => 'SN-DKR-2023-B-01800',
            'invite_code' => Str::upper(Str::random(6)),
        ]);

        $firm->users()->attach($owner->id, ['role' => 'owner']);
        $firm->users()->attach($admin->id, ['role' => 'admin']);
        $firm->users()->attach($member->id, ['role' => 'member']);

        return $firm;
    }

    /** @return array<string, Company> */
    private function createPortfolio(Company $firm): array
    {
        $smes = [];

        // ─── 3 Critiques (overdue > 60 jours) ──────────────────────────────

        $transatlantique = $this->createSme($firm, 'Transatlantique Import-Export SARL', 'essentiel', '+221338710101');
        $this->createTransatlantiqueInvoices($transatlantique);
        $smes['transatlantique'] = $transatlantique;

        $groupeBatisseur = $this->createSme($firm, 'Groupe Bâtisseur SA', 'essentiel', '+221338710102');
        $this->createGroupeBatisseurInvoices($groupeBatisseur);
        $smes['groupe_batisseur'] = $groupeBatisseur;

        $senechimie = $this->createSme($firm, 'SénéChimie SARL', 'essentiel', '+221338710103');
        $this->createSenechimieInvoices($senechimie);
        $smes['senechimie'] = $senechimie;

        // ─── 5 À surveiller (overdue < 60j ou inactif) ──────────────────────

        $couryTextile = $this->createSme($firm, 'Coury Textile SARL', 'basique', '+221338710104');
        $this->createWatchOverdueInvoices($couryTextile, overdueAgoDays: 38);
        $smes['coury_textile'] = $couryTextile;

        $transportNgor = $this->createSme($firm, 'Transport Ngor SARL', 'essentiel', '+221338710105');
        $this->createWatchOverdueInvoices($transportNgor, overdueAgoDays: 28);
        $smes['transport_ngor'] = $transportNgor;

        $sahelCommerce = $this->createSme($firm, 'Sahel Commerce', 'basique', '+221338710106');
        $this->createInactiveInvoices($sahelCommerce, lastInvoiceAgoDays: 42);
        $smes['sahel_commerce'] = $sahelCommerce;

        $ndioumAgro = $this->createSme($firm, 'Ndioum Agro SA', 'essentiel', '+221338710107');
        $this->createInactiveInvoices($ndioumAgro, lastInvoiceAgoDays: 32);
        $smes['ndioum_agro'] = $ndioumAgro;

        $digitalCreation = $this->createSme($firm, 'Digital Création SARL', 'basique', '+221338710108');
        $this->createWatchOverdueInvoices($digitalCreation, overdueAgoDays: 18);
        $smes['digital_creation'] = $digitalCreation;

        // ─── 17 À jour ───────────────────────────────────────────────────────

        $dakarTelecom = $this->createSme($firm, 'Dakar Telecom SA', 'essentiel', '+221338710109');
        $this->createHealthyInvoices($dakarTelecom, count: 5, totalPerInvoice: 680_000);
        $smes['dakar_telecom'] = $dakarTelecom;

        $biomedWest = $this->createSme($firm, 'BioMed West Africa', 'essentiel', '+221338710110');
        $this->createHealthyInvoices($biomedWest, count: 4, totalPerInvoice: 520_000);
        $smes['biomed_west'] = $biomedWest;

        $seneBatiment = $this->createSme($firm, 'SénéBâtiment SA', 'essentiel', '+221338710111');
        $this->createHealthyInvoices($seneBatiment, count: 6, totalPerInvoice: 950_000);
        $smes['sene_batiment'] = $seneBatiment;

        $oryxEnergy = $this->createSme($firm, 'ORYX Energy SARL', 'essentiel', '+221338710112');
        $this->createHealthyInvoices($oryxEnergy, count: 4, totalPerInvoice: 780_000);
        $smes['oryx_energy'] = $oryxEnergy;

        $afridataConsulting = $this->createSme($firm, 'Afridata Consulting', 'essentiel', '+221338710113');
        $this->createHealthyInvoices($afridataConsulting, count: 5, totalPerInvoice: 440_000);
        $smes['afridata_consulting'] = $afridataConsulting;

        $sebikhotane = $this->createSme($firm, 'Sébikhotane Industries', 'basique', '+221338710114');
        $this->createHealthyInvoices($sebikhotane, count: 3, totalPerInvoice: 280_000);
        $smes['sebikhotane'] = $sebikhotane;

        $keurMassar = $this->createSme($firm, 'Keur Massar Commerce', 'basique', '+221338710115');
        $this->createHealthyInvoices($keurMassar, count: 2, totalPerInvoice: 190_000);
        $smes['keur_massar'] = $keurMassar;

        $sunuDigital = $this->createSme($firm, 'Sunu Digital Agency', 'essentiel', '+221338710116');
        $this->createHealthyInvoices($sunuDigital, count: 4, totalPerInvoice: 360_000);
        $smes['sunu_digital'] = $sunuDigital;

        $lougaServices = $this->createSme($firm, 'Louga Services', 'basique', '+221338710117');
        $this->createHealthyInvoices($lougaServices, count: 3, totalPerInvoice: 215_000);
        $smes['louga_services'] = $lougaServices;

        $mbourHotels = $this->createSme($firm, 'Mbour Hotels & Resorts', 'essentiel', '+221338710118');
        $this->createHealthyInvoices($mbourHotels, count: 5, totalPerInvoice: 820_000);
        $smes['mbour_hotels'] = $mbourHotels;

        $thiesConstructions = $this->createSme($firm, 'Thiès Constructions SA', 'essentiel', '+221338710119');
        $this->createHealthyInvoices($thiesConstructions, count: 4, totalPerInvoice: 1_100_000);
        $smes['thies_constructions'] = $thiesConstructions;

        $grandYoffAuto = $this->createSme($firm, 'Grand Yoff Auto', 'basique', '+221338710120');
        $this->createHealthyInvoices($grandYoffAuto, count: 3, totalPerInvoice: 175_000);
        $smes['grand_yoff_auto'] = $grandYoffAuto;

        $almadiesImmo = $this->createSme($firm, 'Almadies Immobilier', 'essentiel', '+221338710121');
        $this->createHealthyInvoices($almadiesImmo, count: 6, totalPerInvoice: 1_300_000);
        $smes['almadies_immo'] = $almadiesImmo;

        $sicapMedia = $this->createSme($firm, 'Sicap Média', 'essentiel', '+221338710122');
        $this->createHealthyInvoices($sicapMedia, count: 3, totalPerInvoice: 320_000);
        $smes['sicap_media'] = $sicapMedia;

        $plateauFinance = $this->createSme($firm, 'Plateau Finance SARL', 'essentiel', '+221338710123');
        $this->createHealthyInvoices($plateauFinance, count: 5, totalPerInvoice: 580_000);
        $smes['plateau_finance'] = $plateauFinance;

        $ziguinchorPeche = $this->createSme($firm, 'Ziguinchor Pêche', 'basique', '+221338710124');
        $this->createHealthyInvoices($ziguinchorPeche, count: 2, totalPerInvoice: 240_000);
        $smes['ziguinchor_peche'] = $ziguinchorPeche;

        $tambacoundaMining = $this->createSme($firm, 'Tambacounda Mining Support', 'basique', '+221338710125');
        $this->createHealthyInvoices($tambacoundaMining, count: 3, totalPerInvoice: 310_000);
        $smes['tambacounda_mining'] = $tambacoundaMining;

        return $smes;
    }

    private function createSme(Company $firm, string $name, string $plan, string $phone): Company
    {
        $owner = User::create([
            'first_name' => SenegalFaker::firstNameMale(),
            'last_name' => SenegalFaker::lastName(),
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
            'started_at' => now()->subMonths(rand(3, 9)),
        ]);

        return $sme;
    }

    /**
     * Transatlantique Import-Export — critique : 8 impayées, ~4 700 000 F en attente, J+68.
     */
    private function createTransatlantiqueInvoices(Company $sme): void
    {
        foreach (range(1, 10) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(8)->addWeeks($i),
                'due_at' => now()->subMonths(7)->addWeeks($i),
                'total' => 1_120_000,
                'amount_paid' => 1_120_000,
            ]);
        }

        foreach (range(1, 8) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Overdue,
                'issued_at' => now()->subDays(82),
                'due_at' => now()->subDays(68),
                'total' => 590_000,
                'amount_paid' => 0,
            ]);
        }
    }

    /**
     * Groupe Bâtisseur SA — critique : 6 impayées, ~5 000 000 F en attente, J+72.
     */
    private function createGroupeBatisseurInvoices(Company $sme): void
    {
        foreach (range(1, 8) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(7)->addWeeks($i),
                'due_at' => now()->subMonths(6)->addWeeks($i),
                'total' => 1_480_000,
                'amount_paid' => 1_480_000,
            ]);
        }

        foreach (range(1, 6) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Overdue,
                'issued_at' => now()->subDays(87),
                'due_at' => now()->subDays(72),
                'total' => 840_000,
                'amount_paid' => 0,
            ]);
        }
    }

    /**
     * SénéChimie SARL — critique : 5 impayées, ~2 500 000 F en attente, J+61.
     */
    private function createSenechimieInvoices(Company $sme): void
    {
        foreach (range(1, 7) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(5)->addWeeks($i),
                'due_at' => now()->subMonths(4)->addWeeks($i),
                'total' => 920_000,
                'amount_paid' => 920_000,
            ]);
        }

        foreach (range(1, 5) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Overdue,
                'issued_at' => now()->subDays(76),
                'due_at' => now()->subDays(61),
                'total' => 500_000,
                'amount_paid' => 0,
            ]);
        }
    }

    private function createWatchOverdueInvoices(Company $sme, int $overdueAgoDays): void
    {
        foreach (range(1, 5) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(4)->addWeeks($i),
                'due_at' => now()->subMonths(3)->addWeeks($i),
                'total' => 460_000,
                'amount_paid' => 460_000,
            ]);
        }

        $this->createInvoice($sme, [
            'reference' => $this->invoiceRef(),
            'status' => InvoiceStatus::Overdue,
            'issued_at' => now()->subDays($overdueAgoDays + 15),
            'due_at' => now()->subDays($overdueAgoDays),
            'total' => 420_000,
            'amount_paid' => 0,
        ]);
    }

    private function createInactiveInvoices(Company $sme, int $lastInvoiceAgoDays): void
    {
        foreach (range(1, 6) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subDays($lastInvoiceAgoDays + (($i - 1) * 15)),
                'due_at' => now()->subDays($lastInvoiceAgoDays - 15 + (($i - 1) * 15)),
                'total' => 350_000,
                'amount_paid' => 350_000,
            ]);
        }
    }

    private function createHealthyInvoices(Company $sme, int $count, int $totalPerInvoice): void
    {
        foreach (range(1, $count) as $i) {
            $isLatest = $i === $count;
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => $isLatest ? now()->subDays(rand(1, 8)) : now()->subMonths(5)->addWeeks($i),
                'due_at' => $isLatest ? now()->addDays(22) : now()->subMonths(4)->addWeeks($i),
                'total' => $totalPerInvoice,
                'amount_paid' => $totalPerInvoice,
            ]);
        }
    }

    /**
     * Commissions sur 6 mois (Nov 2025 – Avr 2026).
     *
     * 17 Essentiel (20 000 F × 15 % = 3 000 F)
     * 8  Basique   (10 000 F × 15 % = 1 500 F)
     * Total mensuel complet : 51 000 + 12 000 = 63 000 F
     *
     * @param  array<string, Company>  $smes
     */
    private function createCommissions(Company $firm, array $smes): void
    {
        /** @var array<string, int> */
        $amounts = [
            // Essentiel × 17
            'transatlantique' => CommissionService::calculate(20_000),
            'groupe_batisseur' => CommissionService::calculate(20_000),
            'senechimie' => CommissionService::calculate(20_000),
            'transport_ngor' => CommissionService::calculate(20_000),
            'ndioum_agro' => CommissionService::calculate(20_000),
            'dakar_telecom' => CommissionService::calculate(20_000),
            'biomed_west' => CommissionService::calculate(20_000),
            'sene_batiment' => CommissionService::calculate(20_000),
            'oryx_energy' => CommissionService::calculate(20_000),
            'afridata_consulting' => CommissionService::calculate(20_000),
            'sunu_digital' => CommissionService::calculate(20_000),
            'mbour_hotels' => CommissionService::calculate(20_000),
            'thies_constructions' => CommissionService::calculate(20_000),
            'almadies_immo' => CommissionService::calculate(20_000),
            'sicap_media' => CommissionService::calculate(20_000),
            'plateau_finance' => CommissionService::calculate(20_000),
            'digital_creation' => CommissionService::calculate(20_000),
            // Basique × 8
            'coury_textile' => CommissionService::calculate(10_000),
            'sahel_commerce' => CommissionService::calculate(10_000),
            'sebikhotane' => CommissionService::calculate(10_000),
            'keur_massar' => CommissionService::calculate(10_000),
            'louga_services' => CommissionService::calculate(10_000),
            'grand_yoff_auto' => CommissionService::calculate(10_000),
            'ziguinchor_peche' => CommissionService::calculate(10_000),
            'tambacounda_mining' => CommissionService::calculate(10_000),
        ];

        foreach ($amounts as $key => $amount) {
            if (! isset($smes[$key])) {
                continue;
            }

            // Mois courant — pending
            Commission::create([
                'accountant_firm_id' => $firm->id,
                'sme_company_id' => $smes[$key]->id,
                'amount' => $amount,
                'period_month' => now()->startOfMonth(),
                'status' => 'pending',
            ]);

            // 5 mois d'historique payés
            foreach (range(1, 5) as $monthsAgo) {
                Commission::create([
                    'accountant_firm_id' => $firm->id,
                    'sme_company_id' => $smes[$key]->id,
                    'amount' => $amount,
                    'period_month' => now()->subMonthsNoOverflow($monthsAgo)->startOfMonth(),
                    'status' => 'paid',
                    'paid_at' => now()->subMonthsNoOverflow($monthsAgo)->endOfMonth(),
                ]);
            }
        }
    }

    /**
     * Historique des versements — 5 mois payés + mois courant en attente.
     * Croissance progressive du portefeuille.
     */
    private function createCommissionPayments(Company $firm): void
    {
        // M-5 : Nov 2025 — 10 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(5)->startOfMonth(),
            'active_clients_count' => 10,
            'amount' => 22_500,
            'paid_at' => now()->subMonthsNoOverflow(4)->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // M-4 : Déc 2025 — 14 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(4)->startOfMonth(),
            'active_clients_count' => 14,
            'amount' => 33_000,
            'paid_at' => now()->subMonthsNoOverflow(3)->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // M-3 : Jan 2026 — 18 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(3)->startOfMonth(),
            'active_clients_count' => 18,
            'amount' => 43_500,
            'paid_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // M-2 : Fév 2026 — 21 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(2)->startOfMonth(),
            'active_clients_count' => 21,
            'amount' => 51_000,
            'paid_at' => now()->subMonthsNoOverflow(1)->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // M-1 : Mar 2026 — 23 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(1)->startOfMonth(),
            'active_clients_count' => 23,
            'amount' => 57_000,
            'paid_at' => now()->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // Mois courant : Avr 2026 — 25 clients — pas encore versé
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->startOfMonth(),
            'active_clients_count' => 25,
            'amount' => 63_000,
            'paid_at' => null,
            'payment_method' => null,
            'status' => 'pending',
        ]);
    }

    /**
     * Invitations variées pour illustrer toutes les pages du module Partenariat.
     *
     * @param  array<string, Company>  $smes
     */
    private function createInvitations(Company $firm, array $smes): void
    {
        // ─── Acceptées récentes (référées, déjà clientes) ───────────────────
        $recentAccepted = [
            ['Dakar Telecom SA', 'Aliou Camara', '+221771900101', 'dakar_telecom', 'essentiel', 8],
            ['BioMed West Africa', 'Ndèye Fatou Diop', '+221771900102', 'biomed_west', 'essentiel', 12],
            ['SénéBâtiment SA', 'Pape Moussa Fall', '+221771900103', 'sene_batiment', 'essentiel', 18],
            ['ORYX Energy SARL', 'Abdou Rahmane Sow', '+221771900104', 'oryx_energy', 'essentiel', 22],
            ['Afridata Consulting', 'Rokhaya Ndiaye', '+221771900105', 'afridata_consulting', 'essentiel', 35],
            ['Sunu Digital Agency', 'Cheikh Ibra Touré', '+221771900106', 'sunu_digital', 'essentiel', 40],
        ];

        foreach ($recentAccepted as [$company, $contact, $phone, $key, $plan, $daysAgo]) {
            if (! isset($smes[$key])) {
                continue;
            }

            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => $plan,
                'channel' => 'whatsapp',
                'status' => 'accepted',
                'expires_at' => now()->subDays($daysAgo - 30)->addDays(30),
                'accepted_at' => now()->subDays($daysAgo - 2),
                'sme_company_id' => $smes[$key]->id,
                'link_opened_at' => now()->subDays($daysAgo - 1),
                'reminder_count' => 0,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo - 2),
            ]);
        }

        // ─── Acceptées plus anciennes ────────────────────────────────────────
        $olderAccepted = [
            ['Mbour Hotels & Resorts', 'Binta Ly', '+221771900107', 'mbour_hotels', 'essentiel'],
            ['Thiès Constructions SA', 'Mamadou Thiam', '+221771900108', 'thies_constructions', 'essentiel'],
            ['Almadies Immobilier', 'Fatou Kiné Badji', '+221771900109', 'almadies_immo', 'essentiel'],
            ['Plateau Finance SARL', 'Elhadj Diallo', '+221771900110', 'plateau_finance', 'essentiel'],
            ['Sébikhotane Industries', 'Souleymane Diouf', '+221771900111', 'sebikhotane', 'basique'],
            ['Louga Services', 'Aminata Cissé', '+221771900112', 'louga_services', 'basique'],
        ];

        foreach ($olderAccepted as [$company, $contact, $phone, $key, $plan]) {
            if (! isset($smes[$key])) {
                continue;
            }

            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => $plan,
                'channel' => 'whatsapp',
                'status' => 'accepted',
                'expires_at' => now()->subMonths(2),
                'accepted_at' => now()->subMonths(3)->addDays(rand(2, 10)),
                'sme_company_id' => $smes[$key]->id,
                'link_opened_at' => now()->subMonths(3)->addDays(rand(1, 3)),
                'reminder_count' => 0,
                'created_at' => now()->subMonths(3)->subDays(2),
                'updated_at' => now()->subMonths(3)->addDays(rand(2, 10)),
            ]);
        }

        // ─── Pending — lien non ouvert (à relancer) ──────────────────────────
        $notOpened = [
            ['Clinique Pasteur Dakar', 'Dr. Ibrahima Bâ', '+221770100201', 2, 'essentiel'],
            ['Sénégal Logistics SA', 'Moussa Dramé', '+221780100202', 4, 'essentiel'],
            ['Boulangerie Aux Délices', 'Aïssatou Seck', '+221760100203', 6, 'basique'],
            ['Garage Sacré-Cœur', 'Pape Ndiaye', '+221770100204', 1, 'basique'],
        ];

        foreach ($notOpened as [$company, $contact, $phone, $daysAgo, $plan]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => $plan,
                'channel' => 'whatsapp',
                'status' => 'pending',
                'expires_at' => now()->addDays(30 - $daysAgo),
                'link_opened_at' => null,
                'last_reminder_at' => null,
                'reminder_count' => 0,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }

        // ─── Pending — lien ouvert mais pas encore inscrit ───────────────────
        $opened = [
            ['Cabinet Dentaire Almadies', 'Dr. Oumar Faye', '+221780100205', 7, 2, 'essentiel'],
            ['Sénégal Shipping', 'Lamine Gueye', '+221770100206', 5, 1, 'essentiel'],
            ['Imprimerie Dakar Sud', 'Mariama Konaré', '+221760100207', 3, 0, 'basique'],
        ];

        foreach ($opened as [$company, $contact, $phone, $daysAgo, $reminderDays, $plan]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => $plan,
                'channel' => 'whatsapp',
                'status' => 'pending',
                'expires_at' => now()->addDays(30 - $daysAgo),
                'link_opened_at' => now()->subDays($daysAgo - 1),
                'last_reminder_at' => $reminderDays > 0 ? now()->subDays($reminderDays) : null,
                'reminder_count' => $reminderDays > 0 ? 1 : 0,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays(max($daysAgo - 1, 0)),
            ]);
        }

        // ─── En inscription (registration commencée) ─────────────────────────
        $registering = [
            ['Pharmacie Jet d\'Eau', 'Seydou Baldé', '+221770100208', 3, 'link'],
            ['Atelier Mécanique Pikine', 'Boubacar Traoré', '+221780100209', 1, 'whatsapp'],
        ];

        foreach ($registering as [$company, $contact, $phone, $daysAgo, $channel]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => 'essentiel',
                'channel' => $channel,
                'status' => 'registering',
                'expires_at' => now()->addDays(30 - $daysAgo),
                'link_opened_at' => now()->subDays($daysAgo),
                'last_reminder_at' => null,
                'reminder_count' => 0,
                'created_at' => now()->subDays($daysAgo + 1),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }

        // ─── Expirées ─────────────────────────────────────────────────────────
        $expired = [
            ['Boutique Médina Couture', 'Khady Fall', '+221780100210', 50],
            ['Restaurant Le Thiébou', 'Ousmane Traoré', '+221770100211', 62],
            ['École Privée Les Étoiles', 'Adja Mbaye', '+221760100212', 75],
        ];

        foreach ($expired as [$company, $contact, $phone, $daysAgo]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => 'basique',
                'channel' => 'whatsapp',
                'status' => 'expired',
                'expires_at' => now()->subDays($daysAgo - 30),
                'link_opened_at' => null,
                'last_reminder_at' => now()->subDays($daysAgo - 8),
                'reminder_count' => 2,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo - 30),
            ]);
        }

        // ─── Via lien partenaire (pas WhatsApp) ──────────────────────────────
        $viaLink = [
            ['Hôtel Ngor Diarama', 'Ibrahima Touré', '+221770100213', 'pending', 10, 'essentiel'],
            ['SuperMarché Tama', 'Fatou Sarr', '+221780100214', 'pending', 3, 'basique'],
        ];

        foreach ($viaLink as [$company, $contact, $phone, $status, $daysAgo, $plan]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => $plan,
                'channel' => 'link',
                'status' => $status,
                'expires_at' => now()->addDays(30 - $daysAgo),
                'link_opened_at' => now()->subDays($daysAgo - 1),
                'last_reminder_at' => null,
                'reminder_count' => 0,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo - 1),
            ]);
        }
    }

    private function invoiceRef(): string
    {
        return 'FYK-FAC-'.Str::upper(Str::random(6));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInvoice(Company $sme, array $attributes): Invoice
    {
        $firstName = SenegalFaker::firstName();
        $lastName = SenegalFaker::lastName();
        $client = Client::create([
            'company_id' => $sme->id,
            'name' => SenegalFaker::companyName(),
            'phone' => SenegalFaker::phone(),
            'email' => SenegalFaker::email($firstName, $lastName),
            'address' => SenegalFaker::address(),
            'tax_id' => 'SN'.strtoupper(fake()->numerify('##########')),
        ]);

        $total = $attributes['total'] ?? 0;
        $subtotal = $attributes['subtotal'] ?? (int) round($total / 1.18);
        $taxAmount = $total - $subtotal;

        /** @var Invoice $invoice */
        $invoice = Invoice::unguarded(fn () => Invoice::create(array_merge([
            'company_id' => $sme->id,
            'client_id' => $client->id,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
        ], $attributes)));

        $lineDescriptions = [
            'Tenue de comptabilité mensuelle',
            'Déclaration fiscale trimestrielle',
            'Révision des comptes annuels',
            'Audit financier',
            'Assistance juridique et fiscale',
            'Formation comptabilité équipe',
            'Mission de commissariat aux comptes',
            'Conseil en gestion financière',
            'Établissement des états financiers',
            'Préparation liasse fiscale',
        ];
        shuffle($lineDescriptions);
        $lineCount = rand(2, 3);
        $descriptions = array_slice($lineDescriptions, 0, $lineCount);

        $weights = array_map(fn () => rand(1, 9), range(1, $lineCount));
        $totalWeight = array_sum($weights);
        $amounts = [];
        $allocated = 0;

        foreach ($weights as $idx => $weight) {
            if ($idx === $lineCount - 1) {
                $amounts[] = $subtotal - $allocated;
            } else {
                $amount = (int) round($subtotal * $weight / $totalWeight);
                $amounts[] = $amount;
                $allocated += $amount;
            }
        }

        foreach ($descriptions as $idx => $description) {
            $lineTotal = $amounts[$idx];
            $qty = rand(1, 4);
            $unitPrice = (int) round($lineTotal / $qty);
            $lineTotal = $unitPrice * $qty;

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $lineTotal,
            ]);
        }

        return $invoice;
    }
}
