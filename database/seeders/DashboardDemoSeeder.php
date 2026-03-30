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

class DashboardDemoSeeder extends Seeder
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
            'email' => 'contact@diallo-associes.sn',
            'address' => '25 Rue Carnot, Plateau',
            'city' => 'Dakar',
            'ninea' => 'SN123456789',
            'rccm' => 'SN-DKR-2024-B-12345',
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

    /**
     * Commissions du mois courant — commission = abonnement × 15 %.
     *
     * Essentiel (20 000 F) × 15 % = 3 000 F
     * Basique   (10 000 F) × 15 % = 1 500 F
     *
     * @param  array<string, Company>  $smes
     */
    private function createCommissions(Company $firm, array $smes): void
    {
        /** @var array<string, int> */
        $amounts = [
            // 11 × 3 000 F = 33 000 F (Essentiel)
            'kane_import' => CommissionService::calculate(20_000),
            'thies_industries' => CommissionService::calculate(20_000),
            'sow_btp' => CommissionService::calculate(20_000),
            'coulibaly_tech' => CommissionService::calculate(20_000),
            'ba_industries' => CommissionService::calculate(20_000),
            'diye_consulting' => CommissionService::calculate(20_000),
            'dakar_pharma' => CommissionService::calculate(20_000),
            'sy_consulting' => CommissionService::calculate(20_000),
            'toure_immobilier' => CommissionService::calculate(20_000),
            'cisse_associes' => CommissionService::calculate(20_000),
            'fall_digital' => CommissionService::calculate(20_000),
            // 7 × 1 500 F = 10 500 F (Basique)
            'mbaye_transport' => CommissionService::calculate(10_000),
            'coury_commerce' => CommissionService::calculate(10_000),
            'diop_services' => CommissionService::calculate(10_000),
            'ndiaye_commerce' => CommissionService::calculate(10_000),
            'traore_agriculture' => CommissionService::calculate(10_000),
            'ly_fashion' => CommissionService::calculate(10_000),
            'kone_services' => CommissionService::calculate(10_000),
        ];
        // Total : 33 000 + 10 500 = 43 500 F ✓

        foreach ($amounts as $key => $amount) {
            if (! isset($smes[$key])) {
                continue;
            }

            Commission::create([
                'accountant_firm_id' => $firm->id,
                'sme_company_id' => $smes[$key]->id,
                'amount' => $amount,
                'period_month' => now()->startOfMonth(),
                'status' => 'pending',
            ]);

            // Historique sur 2 mois
            foreach ([1, 2] as $monthsAgo) {
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
     * @param  array<string, mixed>  $attributes
     */
    private function createInvoice(Company $sme, array $attributes): Invoice
    {
        // Créer un client final si pas fourni
        $clientId = $attributes['client_id'] ?? null;
        if (! $clientId) {
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
            $clientId = $client->id;
        }

        $total = $attributes['total'] ?? 0;
        $taxRate = 18;
        // Calculer HT depuis TTC si pas fourni séparément
        $subtotal = $attributes['subtotal'] ?? (int) round($total / 1.18);
        $taxAmount = $total - $subtotal;

        /** @var Invoice $invoice */
        $invoice = Invoice::unguarded(fn () => Invoice::create(array_merge([
            'company_id' => $sme->id,
            'client_id' => $clientId,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
        ], $attributes)));

        // Créer 2 à 4 lignes de facture avec des montants variés
        $lineCount = rand(2, 4);
        $lineDescriptions = [
            'Prestation de conseil',
            'Tenue de comptabilité',
            'Audit financier',
            'Déclaration fiscale',
            'Formation',
            'Révision des comptes',
            'Mission de commissariat',
            'Assistance juridique',
        ];
        shuffle($lineDescriptions);
        $descriptions = array_slice($lineDescriptions, 0, $lineCount);

        // Répartition aléatoire : on génère des poids (ex: 3, 5, 2) puis on scale
        $weights = array_map(fn () => rand(1, 9), range(1, $lineCount));
        $totalWeight = array_sum($weights);
        $amounts = [];
        $allocated = 0;
        foreach ($weights as $idx => $weight) {
            if ($idx === $lineCount - 1) {
                // Dernière ligne absorbe le reste pour éviter les arrondis
                $amounts[] = $subtotal - $allocated;
            } else {
                $amount = (int) round($subtotal * $weight / $totalWeight);
                $amounts[] = $amount;
                $allocated += $amount;
            }
        }

        foreach ($descriptions as $idx => $description) {
            $lineTotal = $amounts[$idx];
            $qty = rand(1, 5);
            $unitPrice = (int) round($lineTotal / $qty);
            // Recalibrer pour éviter les écarts d'arrondi sur la ligne
            $lineTotal = $unitPrice * $qty;

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'total' => $lineTotal,
            ]);
        }

        return $invoice;
    }

    /**
     * Historique des versements — 3 mois passés payés via Wave.
     */
    private function createCommissionPayments(Company $firm): void
    {
        // Mois M-1 : 16 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(1)->startOfMonth(),
            'active_clients_count' => 16,
            'amount' => 37_500,
            'paid_at' => now()->subMonthsNoOverflow(1)->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // Mois M-2 : 14 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(2)->startOfMonth(),
            'active_clients_count' => 14,
            'amount' => 33_000,
            'paid_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // Mois M-3 : 12 clients actifs
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->subMonthsNoOverflow(3)->startOfMonth(),
            'active_clients_count' => 12,
            'amount' => 28_500,
            'paid_at' => now()->subMonthsNoOverflow(3)->startOfMonth()->addDays(4),
            'payment_method' => 'wave',
            'status' => 'paid',
        ]);

        // Mois courant : pas encore versé
        CommissionPayment::create([
            'accountant_firm_id' => $firm->id,
            'period_month' => now()->startOfMonth(),
            'active_clients_count' => 18,
            'amount' => 43_500,
            'paid_at' => null,
            'payment_method' => null,
            'status' => 'pending',
        ]);
    }

    /**
     * Invitations variées — différents statuts pour la page Invitations.
     *
     * @param  array<string, Company>  $smes
     */
    private function createInvitations(Company $firm, array $smes): void
    {
        // ─── Activées (clients existants référés via invitation) ──────────
        $activatedSmes = ['dakar_pharma', 'coulibaly_tech', 'sy_consulting', 'fall_digital', 'ba_industries'];
        $activatedContacts = [
            ['Amadou Ba', '+221701890001', 'Dakar Pharma'],
            ['Fatou Seck', '+221771230001', 'Coulibaly Tech'],
            ['Ibrahima Sy', '+221781340001', 'Sy Consulting'],
            ['Awa Fall', '+221771450001', 'Fall Digital'],
            ['Mamadou Bâ', '+221761560001', 'Bâ Industries'],
        ];
        foreach ($activatedSmes as $i => $key) {
            if (! isset($smes[$key])) {
                continue;
            }
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $activatedContacts[$i][2],
                'invitee_name' => $activatedContacts[$i][0],
                'invitee_phone' => $activatedContacts[$i][1],
                'recommended_plan' => $i % 2 === 0 ? 'essentiel' : 'basique',
                'channel' => 'whatsapp',
                'status' => 'accepted',
                'expires_at' => now()->addDays(20),
                'accepted_at' => now()->subDays(rand(2, 45)),
                'sme_company_id' => $smes[$key]->id,
                'link_opened_at' => now()->subDays(rand(3, 50)),
                'reminder_count' => 0,
            ]);
        }

        // ─── Pending — lien non ouvert (à relancer) ──────────────────────
        $notOpened = [
            ['Transport Ngor SARL', 'Moussa Diallo', '+221770100001', 3],
            ['Garage Fass Auto', 'Cheikh Ndiaye', '+221780200002', 5],
            ['Imprimerie Plateau', 'Aïda Thiam', '+221760300003', 1],
        ];
        foreach ($notOpened as [$company, $contact, $phone, $daysAgo]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => 'essentiel',
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

        // ─── Pending — lien ouvert mais pas inscrit ──────────────────────
        $opened = [
            ['Boutique Mode HLM', 'Awa Ndiaye', '+221780400004', 7, 2],
            ['Clinique Mamelles', 'Dr. Sarr', '+221760500005', 4, 1],
        ];
        foreach ($opened as [$company, $contact, $phone, $daysAgo, $reminderDays]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => 'essentiel',
                'channel' => 'whatsapp',
                'status' => 'pending',
                'expires_at' => now()->addDays(30 - $daysAgo),
                'link_opened_at' => now()->subDays($daysAgo - 1),
                'last_reminder_at' => now()->subDays($reminderDays),
                'reminder_count' => 1,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($reminderDays),
            ]);
        }

        // ─── En inscription (registration commencée) ────────────────────
        $registering = [
            ['Cabinet Dentaire Fann', 'Dr. Diop', '+221760600006', 2],
            ['Sénégal Shipping', 'Oumar Gueye', '+221770700007', 1],
        ];
        foreach ($registering as [$company, $contact, $phone, $daysAgo]) {
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => 'essentiel',
                'channel' => $daysAgo === 2 ? 'link' : 'whatsapp',
                'status' => 'registering',
                'expires_at' => now()->addDays(30 - $daysAgo),
                'link_opened_at' => now()->subDays($daysAgo),
                'last_reminder_at' => null,
                'reminder_count' => 0,
                'created_at' => now()->subDays($daysAgo + 1),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }

        // ─── Expirées ────────────────────────────────────────────────────
        $expired = [
            ['Keur Digital Legacy', 'Abdou Mbaye', '+221780800008', 45],
            ['Touba Express', 'Serigne Fall', '+221770900009', 60],
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
                'last_reminder_at' => now()->subDays($daysAgo - 5),
                'reminder_count' => 2,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo - 30),
            ]);
        }

        // ─── Invitations supplémentaires activées plus anciennes ─────────
        $olderActivated = [
            ['Ndiaye Commerce', 'Abdoulaye Ndiaye', '+221771010010', 'ndiaye_commerce'],
            ['Traoré Agriculture', 'Moussa Traoré', '+221771110011', 'traore_agriculture'],
            ['Koné Services', 'Ibrahim Koné', '+221771210012', 'kone_services'],
        ];
        foreach ($olderActivated as [$company, $contact, $phone, $key]) {
            if (! isset($smes[$key])) {
                continue;
            }
            PartnerInvitation::create([
                'accountant_firm_id' => $firm->id,
                'token' => Str::random(32),
                'invitee_company_name' => $company,
                'invitee_name' => $contact,
                'invitee_phone' => $phone,
                'recommended_plan' => 'basique',
                'channel' => 'whatsapp',
                'status' => 'accepted',
                'expires_at' => now()->subDays(30),
                'accepted_at' => now()->subMonths(2)->subDays(rand(0, 15)),
                'sme_company_id' => $smes[$key]->id,
                'link_opened_at' => now()->subMonths(2)->subDays(rand(16, 25)),
                'reminder_count' => 0,
                'created_at' => now()->subMonths(3),
                'updated_at' => now()->subMonths(2),
            ]);
        }

        // ─── Invitation via lien partenaire (pas WhatsApp) ──────────────
        PartnerInvitation::create([
            'accountant_firm_id' => $firm->id,
            'token' => Str::random(32),
            'invitee_company_name' => 'Atelier Bois Yoff',
            'invitee_name' => 'Lamine Cissé',
            'invitee_phone' => '+221761310013',
            'recommended_plan' => 'essentiel',
            'channel' => 'link',
            'status' => 'pending',
            'expires_at' => now()->addDays(25),
            'link_opened_at' => now()->subDays(1),
            'last_reminder_at' => null,
            'reminder_count' => 0,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(1),
        ]);
    }
}
