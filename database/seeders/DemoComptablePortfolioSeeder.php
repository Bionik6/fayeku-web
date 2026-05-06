<?php

namespace Database\Seeders;

use App\Enums\Auth\CompanyRole;
use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\Commission;
use App\Models\Compta\CommissionPayment;
use App\Models\Compta\PartnerInvitation;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use App\Models\Shared\User;
use App\Services\Compta\CommissionService;
use Database\Factories\Support\SenegalFaker;
use Database\Seeders\Concerns\GeneratesDemoTaxIds;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Étoffe le portefeuille de Cabinet Ndiaye Conseil créé par
 * DemoAccountsSeeder. Ajoute :
 * - 23 PME anonymes (3 critiques, 5 à surveiller, 15 à jour)
 * - factures de chaque PME (payées + impayées selon le statut)
 * - commissions sur 6 mois
 * - paiements de commissions historiques
 * - invitations partenaires (acceptées, en cours, expirées)
 *
 * Avec Diop Services et Sow BTP (créés par DemoAccountsSeeder), le cabinet
 * gère 25 PME au total — taille suffisante pour peupler le dashboard et
 * tester les filtres/segmentations.
 */
class DemoComptablePortfolioSeeder extends Seeder
{
    use GeneratesDemoTaxIds;

    public function run(): void
    {
        DB::transaction(function (): void {
            $cabinet = $this->resolveCabinet();
            $smes = $this->createPortfolio($cabinet);
            $this->createCommissions($cabinet, $smes);
            $this->createCommissionPayments($cabinet);
            $this->createInvitations($cabinet, $smes);
        });
    }

    private function resolveCabinet(): Company
    {
        return Company::query()
            ->where('type', 'accountant_firm')
            ->where('name', 'Cabinet Ndiaye Conseil')
            ->firstOrFail();
    }

    /** @return array<string, Company> */
    private function createPortfolio(Company $cabinet): array
    {
        $smes = [];

        // ─── 3 Critiques (overdue > 60 jours) ──────────────────────────────
        $smes['transatlantique'] = $this->createSme($cabinet, 'transatlantique', 'Transatlantique Import-Export SARL', 'essentiel', '+221338710101');
        $this->seedHeavyOverdue($smes['transatlantique'], paidCount: 10, paidAmount: 1_120_000, overdueCount: 8, overdueAmount: 590_000, overdueAgo: 68);

        $smes['groupe_batisseur'] = $this->createSme($cabinet, 'groupe_batisseur', 'Groupe Bâtisseur SA', 'essentiel', '+221338710102');
        $this->seedHeavyOverdue($smes['groupe_batisseur'], paidCount: 8, paidAmount: 1_480_000, overdueCount: 6, overdueAmount: 840_000, overdueAgo: 72);

        $smes['senechimie'] = $this->createSme($cabinet, 'senechimie', 'SénéChimie SARL', 'essentiel', '+221338710103');
        $this->seedHeavyOverdue($smes['senechimie'], paidCount: 7, paidAmount: 920_000, overdueCount: 5, overdueAmount: 500_000, overdueAgo: 61);

        // ─── 5 À surveiller (overdue < 60j ou inactif) ─────────────────────
        $smes['coury_textile'] = $this->createSme($cabinet, 'coury_textile', 'Coury Textile SARL', 'basique', '+221338710104');
        $this->seedWatchOverdue($smes['coury_textile'], overdueAgoDays: 38);

        $smes['transport_ngor'] = $this->createSme($cabinet, 'transport_ngor', 'Transport Ngor SARL', 'essentiel', '+221338710105');
        $this->seedWatchOverdue($smes['transport_ngor'], overdueAgoDays: 28);

        $smes['sahel_commerce'] = $this->createSme($cabinet, 'sahel_commerce', 'Sahel Commerce', 'basique', '+221338710106');
        $this->seedInactive($smes['sahel_commerce'], lastInvoiceAgoDays: 42);

        $smes['ndioum_agro'] = $this->createSme($cabinet, 'ndioum_agro', 'Ndioum Agro SA', 'essentiel', '+221338710107');
        $this->seedInactive($smes['ndioum_agro'], lastInvoiceAgoDays: 32);

        $smes['digital_creation'] = $this->createSme($cabinet, 'digital_creation', 'Digital Création SARL', 'basique', '+221338710108');
        $this->seedWatchOverdue($smes['digital_creation'], overdueAgoDays: 18);

        // ─── 15 À jour ──────────────────────────────────────────────────────
        $healthy = [
            ['dakar_telecom', 'Dakar Telecom SA', 'essentiel', '+221338710109', 5, 680_000],
            ['biomed_west', 'BioMed West Africa', 'essentiel', '+221338710110', 4, 520_000],
            ['sene_batiment', 'SénéBâtiment SA', 'essentiel', '+221338710111', 6, 950_000],
            ['oryx_energy', 'ORYX Energy SARL', 'essentiel', '+221338710112', 4, 780_000],
            ['afridata_consulting', 'Afridata Consulting', 'essentiel', '+221338710113', 5, 440_000],
            ['sebikhotane', 'Sébikhotane Industries', 'basique', '+221338710114', 3, 280_000],
            ['keur_massar', 'Keur Massar Commerce', 'basique', '+221338710115', 2, 190_000],
            ['sunu_digital', 'Sunu Digital Agency', 'essentiel', '+221338710116', 4, 360_000],
            ['louga_services', 'Louga Services', 'basique', '+221338710117', 3, 215_000],
            ['mbour_hotels', 'Mbour Hotels & Resorts', 'essentiel', '+221338710118', 5, 820_000],
            ['thies_constructions', 'Thiès Constructions SA', 'essentiel', '+221338710119', 4, 1_100_000],
            ['grand_yoff_auto', 'Grand Yoff Auto', 'basique', '+221338710120', 3, 175_000],
            ['almadies_immo', 'Almadies Immobilier', 'essentiel', '+221338710121', 6, 1_300_000],
            ['sicap_media', 'Sicap Média', 'essentiel', '+221338710122', 3, 320_000],
            ['plateau_finance', 'Plateau Finance SARL', 'essentiel', '+221338710123', 5, 580_000],
        ];

        foreach ($healthy as [$key, $name, $plan, $phone, $invoiceCount, $invoiceAmount]) {
            $smes[$key] = $this->createSme($cabinet, $key, $name, $plan, $phone);
            $this->seedHealthy($smes[$key], count: $invoiceCount, totalPerInvoice: $invoiceAmount);
        }

        return $smes;
    }

    private function createSme(Company $cabinet, string $key, string $name, string $plan, string $phone): Company
    {
        $owner = User::create([
            'first_name' => SenegalFaker::firstNameMale(),
            'last_name' => SenegalFaker::lastName(),
            'phone' => $phone,
            'email' => "owner@{$key}.test",
            'password' => 'password',
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'is_active' => true,
        ]);

        $owner->forceFill([
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ])->save();

        $sme = Company::create([
            'name' => $name,
            'type' => 'sme',
            'plan' => $plan,
            'country_code' => 'SN',
            'phone' => $phone,
        ]);

        $sme->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);

        Subscription::create([
            'company_id' => $sme->id,
            'plan_slug' => $plan,
            'price_paid' => $plan === 'essentiel' ? 20_000 : 10_000,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->startOfMonth()->addMonth(),
            'invited_by_firm_id' => $cabinet->id,
        ]);

        AccountantCompany::create([
            'accountant_firm_id' => $cabinet->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths(rand(3, 9)),
        ]);

        return $sme;
    }

    private function seedHeavyOverdue(Company $sme, int $paidCount, int $paidAmount, int $overdueCount, int $overdueAmount, int $overdueAgo): void
    {
        foreach (range(1, $paidCount) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(8)->addWeeks($i),
                'due_at' => now()->subMonths(7)->addWeeks($i),
                'total' => $paidAmount,
                'amount_paid' => $paidAmount,
            ]);
        }

        foreach (range(1, $overdueCount) as $_) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Overdue,
                'issued_at' => now()->subDays($overdueAgo + 14),
                'due_at' => now()->subDays($overdueAgo),
                'total' => $overdueAmount,
                'amount_paid' => 0,
            ]);
        }
    }

    private function seedWatchOverdue(Company $sme, int $overdueAgoDays): void
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

    private function seedInactive(Company $sme, int $lastInvoiceAgoDays): void
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

    private function seedHealthy(Company $sme, int $count, int $totalPerInvoice): void
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
     * Cabinet Ndiaye gère 25 PME (23 dans ce seeder + Diop Services + Sow BTP).
     * Plans Essentiel × 17, Basique × 8, en accord avec le portefeuille seedé.
     *
     * @param  array<string, Company>  $smes
     */
    private function createCommissions(Company $cabinet, array $smes): void
    {
        // Inclure aussi Diop Services (essentiel) et Sow BTP (basique) seedés
        // par DemoAccountsSeeder pour que le total = 25.
        $diopServices = Company::where('type', 'sme')->where('name', 'Diop Services SARL')->first();
        $sowBtp = Company::where('type', 'sme')->where('name', 'Sow BTP SARL')->first();

        if ($diopServices) {
            $smes['diop_services'] = $diopServices;
        }
        if ($sowBtp) {
            $smes['sow_btp'] = $sowBtp;
        }

        // 14 clients dont la commission du mois courant est déjà versée.
        $paidThisMonth = [
            'dakar_telecom', 'biomed_west', 'sene_batiment', 'oryx_energy',
            'afridata_consulting', 'sunu_digital', 'mbour_hotels', 'thies_constructions',
            'almadies_immo', 'sicap_media', 'plateau_finance',
            'sebikhotane', 'keur_massar', 'louga_services',
        ];

        // Le montant de la commission est dérivé du plan réel de chaque SME
        // (single source of truth : Company.plan) — évite que la liste seedée
        // se désynchronise des prix appliqués aux abonnements.
        foreach ($smes as $key => $sme) {
            $amount = CommissionService::calculate(CommissionService::planMonthlyPrice($sme->plan ?? 'basique'));
            $isPaidThisMonth = in_array($key, $paidThisMonth, strict: true);

            Commission::create([
                'accountant_firm_id' => $cabinet->id,
                'sme_company_id' => $sme->id,
                'amount' => $amount,
                'period_month' => now()->startOfMonth(),
                'status' => $isPaidThisMonth ? 'paid' : 'pending',
                'paid_at' => $isPaidThisMonth ? now()->subDays(rand(1, 5)) : null,
            ]);

            // 5 mois d'historique payés.
            foreach (range(1, 5) as $monthsAgo) {
                Commission::create([
                    'accountant_firm_id' => $cabinet->id,
                    'sme_company_id' => $sme->id,
                    'amount' => $amount,
                    'period_month' => now()->subMonthsNoOverflow($monthsAgo)->startOfMonth(),
                    'status' => 'paid',
                    'paid_at' => now()->subMonthsNoOverflow($monthsAgo)->endOfMonth(),
                ]);
            }
        }
    }

    /**
     * Croissance progressive du portefeuille sur 6 mois.
     */
    private function createCommissionPayments(Company $cabinet): void
    {
        $payments = [
            [5, 10, 22_500],
            [4, 14, 33_000],
            [3, 18, 43_500],
            [2, 21, 51_000],
            [1, 23, 57_000],
        ];

        foreach ($payments as [$monthsAgo, $clients, $amount]) {
            CommissionPayment::create([
                'accountant_firm_id' => $cabinet->id,
                'period_month' => now()->subMonthsNoOverflow($monthsAgo)->startOfMonth(),
                'active_clients_count' => $clients,
                'amount' => $amount,
                'paid_at' => now()->subMonthsNoOverflow($monthsAgo - 1)->startOfMonth()->addDays(4),
                'payment_method' => 'wave',
                'status' => 'paid',
            ]);
        }

        // Mois courant en attente (25 clients actifs).
        CommissionPayment::create([
            'accountant_firm_id' => $cabinet->id,
            'period_month' => now()->startOfMonth(),
            'active_clients_count' => 25,
            'amount' => 63_000,
            'paid_at' => null,
            'payment_method' => null,
            'status' => 'pending',
        ]);
    }

    /**
     * Invitations couvrant tous les statuts (acceptée, pending, expirée…).
     *
     * @param  array<string, Company>  $smes
     */
    private function createInvitations(Company $cabinet, array $smes): void
    {
        $cabinetUserId = $cabinet->users()->orderBy('users.created_at')->first()?->id;

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
                'accountant_firm_id' => $cabinet->id,
                'created_by_user_id' => $cabinetUserId,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'invitee_email' => "owner@{$key}.test",
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
                'accountant_firm_id' => $cabinet->id,
                'created_by_user_id' => $cabinetUserId,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'invitee_email' => "owner@{$key}.test",
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

        $notOpened = [
            ['Clinique Pasteur Dakar', 'Dr. Ibrahima Bâ', '+221770100201', 2, 'essentiel'],
            ['Sénégal Logistics SA', 'Moussa Dramé', '+221780100202', 4, 'essentiel'],
            ['Boulangerie Aux Délices', 'Aïssatou Seck', '+221760100203', 6, 'basique'],
            ['Garage Sacré-Cœur', 'Pape Ndiaye', '+221770100204', 1, 'basique'],
        ];

        foreach ($notOpened as [$company, $contact, $phone, $daysAgo, $plan]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $cabinet->id,
                'created_by_user_id' => $cabinetUserId,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'invitee_email' => $this->inviteeEmailFor($company),
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

        $opened = [
            ['Cabinet Dentaire Almadies', 'Dr. Oumar Faye', '+221780100205', 7, 2, 'essentiel'],
            ['Sénégal Shipping', 'Lamine Gueye', '+221770100206', 5, 1, 'essentiel'],
            ['Imprimerie Dakar Sud', 'Mariama Konaré', '+221760100207', 3, 0, 'basique'],
        ];

        foreach ($opened as [$company, $contact, $phone, $daysAgo, $reminderDays, $plan]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $cabinet->id,
                'created_by_user_id' => $cabinetUserId,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'invitee_email' => $this->inviteeEmailFor($company),
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

        $registering = [
            ['Pharmacie Jet d\'Eau', 'Seydou Baldé', '+221770100208', 3, 'link'],
            ['Atelier Mécanique Pikine', 'Boubacar Traoré', '+221780100209', 1, 'whatsapp'],
        ];

        foreach ($registering as [$company, $contact, $phone, $daysAgo, $channel]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $cabinet->id,
                'created_by_user_id' => $cabinetUserId,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'invitee_email' => $this->inviteeEmailFor($company),
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

        $expired = [
            ['Boutique Médina Couture', 'Khady Fall', '+221780100210', 50],
            ['Restaurant Le Thiébou', 'Ousmane Traoré', '+221770100211', 62],
            ['École Privée Les Étoiles', 'Adja Mbaye', '+221760100212', 75],
        ];

        foreach ($expired as [$company, $contact, $phone, $daysAgo]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $cabinet->id,
                'created_by_user_id' => $cabinetUserId,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'invitee_email' => $this->inviteeEmailFor($company),
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
    }

    private function invoiceRef(): string
    {
        return 'FYK-FAC-'.Str::upper(Str::random(6));
    }

    /**
     * Synthesize a deterministic invitee email from a company name. Used so
     * pending/registering/expired invitations carry an `invitee_email` that
     * pre-fills the SME registration form when the link is clicked.
     */
    private function inviteeEmailFor(string $companyName): string
    {
        return 'contact@'.Str::slug($companyName).'.test';
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
            'tax_id' => $this->demoTaxId(),
        ]);

        $total = $attributes['total'] ?? 0;
        $subtotal = $attributes['subtotal'] ?? (int) round($total / 1.18);
        $taxAmount = $total - $subtotal;

        // Derive lifecycle timestamps so portfolio invoices have a coherent
        // activity feed when accountants drill in: sent_at on issue day, paid_at
        // shortly before due date for paid invoices.
        $status = $attributes['status'] ?? null;
        $issuedAt = $attributes['issued_at'] ?? null;
        $dueAt = $attributes['due_at'] ?? null;

        if (! array_key_exists('sent_at', $attributes) && $issuedAt && $status !== InvoiceStatus::Draft) {
            $attributes['sent_at'] = $issuedAt;
        }
        if (! array_key_exists('paid_at', $attributes) && $status === InvoiceStatus::Paid && $dueAt) {
            $attributes['paid_at'] = (clone $dueAt)->subDays(rand(1, 5));
        }

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
