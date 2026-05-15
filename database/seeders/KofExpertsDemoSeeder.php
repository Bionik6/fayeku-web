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
 * Compte démo dédié au cabinet KOF EXPERTS (prospect en cours de qualification).
 *
 * ┌── Login principal ──────────────────────────────────────────────────────┐
 * │ Email          fayeku-demo@kof-experts.sn                                │
 * │ Mot de passe   KofDemo2026!                                              │
 * │                                                                          │
 * │ Owners PME du portefeuille : owner@<key>.demo  /  DemoPME2026!           │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * Profil seedé : cabinet « KOF Experts » + 2 collaborateurs, 12 PME en
 * portefeuille (2 critiques, 3 à surveiller, 7 à jour), 6 mois d'historique
 * de commissions et de paiements, invitations partenaires couvrant tous les
 * statuts (acceptées, pending, opened, registering, expirées).
 */
class KofExpertsDemoSeeder extends Seeder
{
    use GeneratesDemoTaxIds;

    private const OWNER_EMAIL = 'fayeku-demo@kof-experts.sn';

    private const CABINET_PASSWORD = 'KofDemo2026!';

    private const SME_PASSWORD = 'DemoPME2026!';

    public function run(): void
    {
        DB::transaction(function (): void {
            $cabinet = $this->createCabinet();
            $smes = $this->createPortfolio($cabinet);
            $this->createCommissions($cabinet, $smes);
            $this->createCommissionPayments($cabinet, totalClients: count($smes));
            $this->createInvitations($cabinet, $smes);
        });
    }

    private function createCabinet(): Company
    {
        $owner = $this->createVerifiedUser(
            firstName: 'Mamadou',
            lastName: 'Sall',
            phone: '+221774500001',
            email: self::OWNER_EMAIL,
            profileType: 'accountant_firm',
        );

        $adminMan = $this->createVerifiedUser(
            firstName: 'Alioune',
            lastName: 'Fall',
            phone: '+221774500002',
            email: 'alioune@kof-experts.demo',
            profileType: 'accountant_firm',
        );

        $adminWoman = $this->createVerifiedUser(
            firstName: 'Awa',
            lastName: 'Niang',
            phone: '+221774500003',
            email: 'awa@kof-experts.demo',
            profileType: 'accountant_firm',
        );

        $cabinet = Company::create([
            'name' => 'KOF Experts',
            'type' => 'accountant_firm',
            'plan' => 'gold',
            'country_code' => 'SN',
            'phone' => '+221338500100',
            'email' => 'contact@kof-experts.demo',
            'sender_name' => 'Mamadou Sall',
            'sender_role' => 'Responsable de contrôle interne',
            'address' => '12 Avenue Léopold Sédar Senghor, Plateau',
            'city' => 'Dakar',
            'sector' => 'Expertise comptable & audit',
            'legal_form' => 'SARL',
            'ninea' => 'SN20240500',
            'rccm' => 'SN-DKR-2024-B-50100',
            'setup_completed_at' => now()->subMonths(6),
            'invite_code' => Str::upper(Str::random(6)),
        ]);

        $cabinet->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);
        $cabinet->users()->attach($adminMan->id, ['role' => CompanyRole::Admin->value]);
        $cabinet->users()->attach($adminWoman->id, ['role' => CompanyRole::Admin->value]);

        return $cabinet;
    }

    /** @return array<string, Company> */
    private function createPortfolio(Company $cabinet): array
    {
        $smes = [];

        // ─── 2 Critiques (overdue > 60 jours) ──────────────────────────────
        $smes['kof_atlantique'] = $this->createSme($cabinet, 'kof_atlantique', 'Atlantique Distribution SARL', 'essentiel', '+221338500201', monthsAgo: 7);
        $this->seedHeavyOverdue($smes['kof_atlantique'], paidCount: 9, paidAmount: 1_040_000, overdueCount: 6, overdueAmount: 620_000, overdueAgo: 65);
        $this->seedCurrentMonthActivity($smes['kof_atlantique'], averageAmount: 720_000, count: 3, paidCount: 0);

        $smes['kof_senebat'] = $this->createSme($cabinet, 'kof_senebat', 'SénéBâti Construction SA', 'essentiel', '+221338500202', monthsAgo: 6);
        $this->seedHeavyOverdue($smes['kof_senebat'], paidCount: 7, paidAmount: 1_320_000, overdueCount: 5, overdueAmount: 780_000, overdueAgo: 71);
        $this->seedCurrentMonthActivity($smes['kof_senebat'], averageAmount: 950_000, count: 3, paidCount: 1);

        // ─── 3 À surveiller (overdue récent ou inactif) ────────────────────
        $smes['kof_textile_kaolack'] = $this->createSme($cabinet, 'kof_textile_kaolack', 'Textile Kaolack SARL', 'basique', '+221338500203', monthsAgo: 5);
        $this->seedWatchOverdue($smes['kof_textile_kaolack'], overdueAgoDays: 32);
        $this->seedCurrentMonthActivity($smes['kof_textile_kaolack'], averageAmount: 380_000, count: 3, paidCount: 1);

        $smes['kof_transport_thies'] = $this->createSme($cabinet, 'kof_transport_thies', 'Transport Thiès SARL', 'essentiel', '+221338500204', monthsAgo: 4);
        $this->seedWatchOverdue($smes['kof_transport_thies'], overdueAgoDays: 22);
        $this->seedCurrentMonthActivity($smes['kof_transport_thies'], averageAmount: 460_000, count: 3, paidCount: 2);

        $smes['kof_agro_casamance'] = $this->createSme($cabinet, 'kof_agro_casamance', 'Casamance Agro SA', 'essentiel', '+221338500205', monthsAgo: 5);
        $this->seedInactive($smes['kof_agro_casamance'], lastInvoiceAgoDays: 38);
        $this->seedCurrentMonthActivity($smes['kof_agro_casamance'], averageAmount: 340_000, count: 2, paidCount: 1);

        // ─── 7 À jour ───────────────────────────────────────────────────────
        $healthy = [
            ['kof_pharmacie_liberte', 'Pharmacie Liberté SARL', 'essentiel', '+221338500206', 5, 540_000, 8],
            ['kof_digital_ndakaaru', 'Digital Ndakaaru Agency', 'essentiel', '+221338500207', 4, 460_000, 4],
            ['kof_btp_almadies', 'Almadies BTP SA', 'essentiel', '+221338500208', 6, 1_180_000, 6],
            ['kof_resto_terrasses', 'Restaurant Les Terrasses', 'basique', '+221338500209', 3, 230_000, 5],
            ['kof_immo_yoff', 'Yoff Immobilier SARL', 'essentiel', '+221338500210', 5, 920_000, 7],
            ['kof_clinique_mermoz', 'Clinique Mermoz SA', 'essentiel', '+221338500211', 4, 680_000, 3],
            ['kof_boulangerie_sicap', 'Boulangerie Sicap', 'basique', '+221338500212', 3, 165_000, 4],
        ];

        foreach ($healthy as [$key, $name, $plan, $phone, $invoiceCount, $invoiceAmount, $monthsAgo]) {
            $smes[$key] = $this->createSme($cabinet, $key, $name, $plan, $phone, $monthsAgo);
            $this->seedHealthy($smes[$key], count: $invoiceCount, totalPerInvoice: $invoiceAmount);
            $this->seedCurrentMonthActivity($smes[$key], averageAmount: $invoiceAmount, count: 3, paidCount: 2);
        }

        return $smes;
    }

    /**
     * Produit 2 à 3 factures émises dans le mois courant pour qu'à l'ouverture
     * de la fiche client le « CA facturé du mois » ne soit jamais vide.
     * Les premières $paidCount sont déjà encaissées (paid_at entre l'émission
     * et aujourd'hui), le reste est en attente (Sent) avec une échéance à
     * 30 jours.
     */
    private function seedCurrentMonthActivity(Company $sme, int $averageAmount, int $count, int $paidCount): void
    {
        $today = now();
        $startOfMonth = $today->copy()->startOfMonth();
        $daysElapsed = max(1, $today->day - 1);

        foreach (range(1, $count) as $i) {
            $issueOffset = (int) round($daysElapsed * $i / ($count + 1));
            $issuedAt = $startOfMonth->copy()->addDays($issueOffset)->setTime(9, 30);
            $dueAt = $issuedAt->copy()->addDays(30);
            $amount = (int) round($averageAmount * (0.85 + (mt_rand(0, 30) / 100)));
            $isPaid = $i <= $paidCount;

            $paidAt = null;
            if ($isPaid) {
                $daysSinceIssue = max(1, (int) $issuedAt->diffInDays($today));
                $paidAt = $issuedAt->copy()->addDays(rand(1, $daysSinceIssue))->setTime(14, 0);
            }

            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => $isPaid ? InvoiceStatus::Paid : InvoiceStatus::Sent,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'total' => $amount,
                'amount_paid' => $isPaid ? $amount : 0,
                'paid_at' => $paidAt,
            ]);
        }
    }

    private function createSme(Company $cabinet, string $key, string $name, string $plan, string $phone, int $monthsAgo): Company
    {
        $owner = User::create([
            'first_name' => SenegalFaker::firstNameMale(),
            'last_name' => SenegalFaker::lastName(),
            'phone' => $phone,
            'email' => "owner@{$key}.demo",
            'password' => self::SME_PASSWORD,
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
            'setup_completed_at' => now()->subMonths($monthsAgo)->addDays(2),
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
            'started_at' => now()->subMonths($monthsAgo),
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
        foreach (range(1, 4) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subMonths(4)->addWeeks($i),
                'due_at' => now()->subMonths(3)->addWeeks($i),
                'total' => 420_000,
                'amount_paid' => 420_000,
            ]);
        }

        $this->createInvoice($sme, [
            'reference' => $this->invoiceRef(),
            'status' => InvoiceStatus::Overdue,
            'issued_at' => now()->subDays($overdueAgoDays + 15),
            'due_at' => now()->subDays($overdueAgoDays),
            'total' => 380_000,
            'amount_paid' => 0,
        ]);
    }

    private function seedInactive(Company $sme, int $lastInvoiceAgoDays): void
    {
        foreach (range(1, 5) as $i) {
            $this->createInvoice($sme, [
                'reference' => $this->invoiceRef(),
                'status' => InvoiceStatus::Paid,
                'issued_at' => now()->subDays($lastInvoiceAgoDays + (($i - 1) * 15)),
                'due_at' => now()->subDays($lastInvoiceAgoDays - 15 + (($i - 1) * 15)),
                'total' => 310_000,
                'amount_paid' => 310_000,
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
     * Commissions sur 6 mois pour les 12 PME seedées. Le mois courant est
     * pending pour 5 PME (paiement à venir) et déjà versé pour les 7 autres.
     *
     * @param  array<string, Company>  $smes
     */
    private function createCommissions(Company $cabinet, array $smes): void
    {
        $paidThisMonth = [
            'kof_pharmacie_liberte',
            'kof_digital_ndakaaru',
            'kof_btp_almadies',
            'kof_resto_terrasses',
            'kof_immo_yoff',
            'kof_clinique_mermoz',
            'kof_boulangerie_sicap',
        ];

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
     * Historique de croissance progressive du portefeuille sur 6 mois.
     */
    private function createCommissionPayments(Company $cabinet, int $totalClients): void
    {
        $payments = [
            [5, 5, 11_500],
            [4, 7, 16_500],
            [3, 9, 21_000],
            [2, 10, 24_000],
            [1, 11, 27_500],
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

        CommissionPayment::create([
            'accountant_firm_id' => $cabinet->id,
            'period_month' => now()->startOfMonth(),
            'active_clients_count' => $totalClients,
            'amount' => 30_000,
            'paid_at' => null,
            'payment_method' => null,
            'status' => 'pending',
        ]);
    }

    /**
     * Invitations couvrant tous les statuts pour montrer la page partenaires
     * sous toutes ses facettes (acceptée, pending, opened, registering, expirée).
     *
     * @param  array<string, Company>  $smes
     */
    private function createInvitations(Company $cabinet, array $smes): void
    {
        $cabinetUserId = $cabinet->users()->orderBy('users.created_at')->first()?->id;

        $recentAccepted = [
            ['Pharmacie Liberté SARL', 'Aliou Camara', '+221771500101', 'kof_pharmacie_liberte', 'essentiel', 8],
            ['Digital Ndakaaru Agency', 'Ndèye Fatou Diop', '+221771500102', 'kof_digital_ndakaaru', 'essentiel', 14],
            ['Almadies BTP SA', 'Pape Moussa Fall', '+221771500103', 'kof_btp_almadies', 'essentiel', 21],
            ['Yoff Immobilier SARL', 'Abdou Rahmane Sow', '+221771500104', 'kof_immo_yoff', 'essentiel', 28],
            ['Clinique Mermoz SA', 'Dr. Rokhaya Ndiaye', '+221771500105', 'kof_clinique_mermoz', 'essentiel', 36],
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
                'invitee_email' => "owner@{$key}.demo",
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
            ['Restaurant Les Terrasses', 'Binta Ly', '+221771500106', 'kof_resto_terrasses', 'basique'],
            ['Boulangerie Sicap', 'Souleymane Diouf', '+221771500107', 'kof_boulangerie_sicap', 'basique'],
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
                'invitee_email' => "owner@{$key}.demo",
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
            ['Clinique Pasteur Dakar', 'Dr. Ibrahima Bâ', '+221770500201', 2, 'essentiel'],
            ['Sénégal Logistics SA', 'Moussa Dramé', '+221780500202', 4, 'essentiel'],
            ['Garage Sacré-Cœur', 'Pape Ndiaye', '+221770500203', 6, 'basique'],
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
            ['Cabinet Dentaire Almadies', 'Dr. Oumar Faye', '+221780500204', 7, 2, 'essentiel'],
            ['Imprimerie Dakar Sud', 'Mariama Konaré', '+221760500205', 3, 0, 'basique'],
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
            ['Atelier Mécanique Pikine', 'Boubacar Traoré', '+221780500206', 1, 'whatsapp'],
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
            ['Boutique Médina Couture', 'Khady Fall', '+221780500207', 52],
            ['École Privée Les Étoiles', 'Adja Mbaye', '+221760500208', 68],
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

    private function createVerifiedUser(string $firstName, string $lastName, string $phone, string $email, string $profileType): User
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $email,
            'password' => self::CABINET_PASSWORD,
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

    private function inviteeEmailFor(string $companyName): string
    {
        return 'contact@'.Str::slug($companyName).'.demo';
    }

    private function invoiceRef(): string
    {
        return 'KOF-FAC-'.Str::upper(Str::random(6));
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
