<?php

namespace Database\Seeders;

use App\Enums\Auth\CompanyRole;
use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\QuoteStatus;
use App\Enums\PME\ReminderChannel;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use App\Models\PME\Quote;
use App\Models\PME\QuoteLine;
use App\Models\PME\Reminder;
use App\Models\Shared\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Showcase seed — PME profile
 *
 * Credentials : +221776200001 / passer1234
 * Company     : Kaay Digital SARL (services numériques, Dakar)
 */
class ShowcasePmeSeeder extends Seeder
{
    private Company $company;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->createCompany();
            $this->seedInvoices();
            $this->seedQuotes();
        });
    }

    private function createCompany(): void
    {
        $owner = User::query()->create([
            'first_name' => 'Mariama',
            'last_name' => 'Diop',
            'phone' => '+221776200001',
            'password' => 'passer1234',
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $collaborator = User::query()->create([
            'first_name' => 'Khadim',
            'last_name' => 'Diop',
            'phone' => '+221776200002',
            'password' => 'passer1234',
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $this->company = Company::query()->create([
            'name' => 'Kaay Digital SARL',
            'type' => 'sme',
            'plan' => 'essentiel',
            'country_code' => 'SN',
            'phone' => '+221338700001',
            'email' => 'contact@kaaydigital.sn',
            'address' => '42 Rue Wagane Diouf, Plateau',
            'city' => 'Dakar',
            'ninea' => 'SN20260042',
            'rccm' => 'SN-DKR-2024-B-04200',
            'sector' => 'Services numériques',
        ]);

        $this->company->users()->attach($owner->id, ['role' => CompanyRole::Owner->value]);
        $this->company->users()->attach($collaborator->id, ['role' => CompanyRole::Member->value]);

        Subscription::query()->create([
            'company_id' => $this->company->id,
            'plan_slug' => 'essentiel',
            'price_paid' => 20_000,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->startOfMonth()->addMonth(),
            'cancelled_at' => null,
            'invited_by_firm_id' => null,
        ]);
    }

    private function seedInvoices(): void
    {
        // ── Clients ────────────────────────────────────────────────────────────
        $orange = $this->client('Orange Sénégal SA', '+221338600001', 'dsi@orange.sn', '21 Rue Léopold Sédar Senghor, Plateau', 'SN2024ORA0001');
        $ecobank = $this->client('Ecobank Sénégal SA', '+221338600002', 'compta@ecobank.sn', '4 Allée Robert Delmas, Plateau', 'SN2024ECO0002');
        $adie = $this->client('Agence de l\'Informatique de l\'État', '+221338600003', 'dsi@adie.sn', 'Route de l\'Aéroport Km 3, Dakar', 'SN2024ADI0003');
        $planInternational = $this->client('Plan International Sénégal', '+221338600004', 'finance@plan-senegal.org', 'Mermoz Nord, Rue 1, Dakar', 'SN2024PLN0004');
        $wari = $this->client('Wari Sénégal SA', '+221338600005', 'direction@wari.sn', 'Immeuble Fayçal, Rue Huart, Dakar', 'SN2024WAR0005');
        $totalEnergies = $this->client('TotalEnergies Marketing SN', '+221338600006', 'compta@totalenergies.sn', 'Route de la Corniche Ouest, Dakar', 'SN2024TOT0006');
        $lafarge = $this->client('LafargeHolcim Sénégal', '+221338600007', 'dsi@lafarge.sn', 'Zone Industrielle de Rufisque, Dakar', 'SN2024LAF0007');
        $cbao = $this->client('CBAO Groupe Attijariwafa', '+221338600008', 'it@cbao.sn', '1 Place de l\'Indépendance, Plateau', 'SN2024CBA0008');
        $auchan = $this->client('AUCHAN Sénégal', '+221338600009', 'dsi@auchan.sn', 'Route du Front de Terre, Liberté 6', 'SN2024AUC0009');
        $kirene = $this->client('Kirène SA', '+221338600010', 'direction@kirene.sn', 'Route de Rufisque, Diamniadio', 'SN2024KIR0010');
        $senelec = $this->client('SENELEC', '+221338600011', 'it@senelec.sn', '28 Rue Vincent, Plateau', 'SN2024SEN0011');
        $airSenegal = $this->client('Air Sénégal SA', '+221338600012', 'compta@airsenegal.sn', 'Aéroport International AIBD, Diass', 'SN2024AIR0012');

        // ── Décembre 2025 — 4 factures payées ─────────────────────────────────
        $this->invoice($orange, 'FYK-FAC-KD0101', InvoiceStatus::Paid, 1_200_000,
            issuedAt: '2025-12-03', dueAt: '2026-01-02', paidAt: '2025-12-28',
            lines: [
                ['Développement application mobile Orange Money (iOS & Android)', 1, 950_000],
                ['Tests et assurance qualité', 1, 250_000],
            ]
        );

        $this->invoice($ecobank, 'FYK-FAC-KD0102', InvoiceStatus::Paid, 1_800_000,
            issuedAt: '2025-12-08', dueAt: '2026-01-07', paidAt: '2026-01-08',
            lines: [
                ['Audit sécurité du système d\'information bancaire', 1, 1_400_000],
                ['Rapport d\'audit et plan de remédiation', 1, 400_000],
            ]
        );

        $this->invoice($adie, 'FYK-FAC-KD0103', InvoiceStatus::Paid, 850_000,
            issuedAt: '2025-12-12', dueAt: '2026-01-11', paidAt: '2025-12-30',
            lines: [
                ['Formation cloud AWS — 10 jours (20 agents)', 10, 75_000],
                ['Support post-formation (1 mois)', 1, 100_000],
            ]
        );

        $this->invoice($wari, 'FYK-FAC-KD0104', InvoiceStatus::Paid, 650_000,
            issuedAt: '2025-12-20', dueAt: '2026-01-19', paidAt: '2026-01-03',
            lines: [
                ['Intégration API de paiement mobile (Wave & Orange Money)', 1, 500_000],
                ['Documentation technique et tests d\'intégration', 1, 150_000],
            ]
        );

        // ── Janvier 2026 — 5 factures payées ──────────────────────────────────
        $this->invoice($orange, 'FYK-FAC-KD0201', InvoiceStatus::Paid, 420_000,
            issuedAt: '2026-01-05', dueAt: '2026-02-04', paidAt: '2026-02-05',
            lines: [
                ['Maintenance corrective plateforme Orange Money — Janv.', 1, 320_000],
                ['Support technique prioritaire (20 tickets)', 20, 5_000],
            ]
        );

        $this->invoice($cbao, 'FYK-FAC-KD0202', InvoiceStatus::Paid, 1_500_000,
            issuedAt: '2026-01-10', dueAt: '2026-02-09', paidAt: '2026-02-10',
            lines: [
                ['Développement module CRM Clientèle CBAO', 1, 1_200_000],
                ['Intégration avec core banking Oracle FLEXCUBE', 1, 300_000],
            ]
        );

        $this->invoice($planInternational, 'FYK-FAC-KD0203', InvoiceStatus::Paid, 980_000,
            issuedAt: '2026-01-14', dueAt: '2026-02-13', paidAt: '2026-02-15',
            lines: [
                ['Développement portail de gestion de projets terrain', 1, 750_000],
                ['Formation équipe (3 jours)', 3, 80_000],
                ['Hébergement et maintenance (6 mois)', 1, 80_000],
            ]
        );

        $this->invoice($totalEnergies, 'FYK-FAC-KD0204', InvoiceStatus::Paid, 720_000,
            issuedAt: '2026-01-18', dueAt: '2026-02-17', paidAt: '2026-02-12',
            lines: [
                ['Développement tableau de bord BI (Power BI + connecteurs)', 1, 580_000],
                ['Déploiement et formation administrateurs (2 jours)', 2, 70_000],
            ]
        );

        $this->invoice($lafarge, 'FYK-FAC-KD0205', InvoiceStatus::Paid, 2_100_000,
            issuedAt: '2026-01-22', dueAt: '2026-02-21', paidAt: '2026-02-20',
            lines: [
                ['Migration ERP Sage X3 — Phase 1 : analyse & paramétrage', 1, 1_600_000],
                ['Migration des données historiques (5 ans)', 1, 500_000],
            ]
        );

        // ── Février 2026 — 4 factures payées ──────────────────────────────────
        $this->invoice($adie, 'FYK-FAC-KD0301', InvoiceStatus::Paid, 1_400_000,
            issuedAt: '2026-02-03', dueAt: '2026-03-05', paidAt: '2026-03-10',
            lines: [
                ['Refonte du dashboard SIGTAS (Système de Gestion Fiscale)', 1, 1_100_000],
                ['Tests fonctionnels et recettage métier', 1, 300_000],
            ]
        );

        $this->invoice($auchan, 'FYK-FAC-KD0302', InvoiceStatus::Paid, 960_000,
            issuedAt: '2026-02-10', dueAt: '2026-03-12', paidAt: '2026-03-08',
            lines: [
                ['Application mobile programme de fidélité AUCHAN', 1, 780_000],
                ['Backend API + tableau de bord administrateur', 1, 180_000],
            ]
        );

        $this->invoice($kirene, 'FYK-FAC-KD0303', InvoiceStatus::Paid, 750_000,
            issuedAt: '2026-02-14', dueAt: '2026-03-16', paidAt: '2026-03-05',
            lines: [
                ['Développement plateforme e-commerce B2B Kirène', 1, 600_000],
                ['Intégration paiement en ligne et livraison', 1, 150_000],
            ]
        );

        $this->invoice($senelec, 'FYK-FAC-KD0304', InvoiceStatus::Paid, 1_100_000,
            issuedAt: '2026-02-20', dueAt: '2026-03-22', paidAt: '2026-03-18',
            lines: [
                ['Refonte portail client SENELEC (web + mobile)', 1, 850_000],
                ['Module de suivi consommation en temps réel', 1, 250_000],
            ]
        );

        // ── Mars 2026 — 6 factures payées ─────────────────────────────────────
        $this->invoice($orange, 'FYK-FAC-KD0401', InvoiceStatus::Paid, 1_800_000,
            issuedAt: '2026-03-02', dueAt: '2026-04-01', paidAt: '2026-03-12',
            lines: [
                ['Déploiement infrastructure réseau Orange — Phase 2', 1, 1_400_000],
                ['Supervision et monitoring (outils Zabbix + Grafana)', 1, 400_000],
            ]
        );

        $this->invoice($cbao, 'FYK-FAC-KD0402', InvoiceStatus::Paid, 2_500_000,
            issuedAt: '2026-03-05', dueAt: '2026-04-04', paidAt: '2026-03-20',
            lines: [
                ['Intégration core banking Oracle FLEXCUBE — Module virements SEPA', 1, 2_000_000],
                ['Tests de charge et sécurité (PCI-DSS)', 1, 500_000],
            ]
        );

        $this->invoice($airSenegal, 'FYK-FAC-KD0403', InvoiceStatus::Paid, 1_300_000,
            issuedAt: '2026-03-08', dueAt: '2026-04-07', paidAt: '2026-03-15',
            lines: [
                ['Développement application de gestion des vols internes', 1, 1_000_000],
                ['Module de gestion des équipages', 1, 300_000],
            ]
        );

        $this->invoice($ecobank, 'FYK-FAC-KD0404', InvoiceStatus::Paid, 620_000,
            issuedAt: '2026-03-11', dueAt: '2026-04-10', paidAt: '2026-03-22',
            lines: [
                ['Formation cybersécurité — 15 collaborateurs (3 jours)', 3, 150_000],
                ['Exercice de simulation d\'intrusion (pentest)', 1, 170_000],
            ]
        );

        $this->invoice($planInternational, 'FYK-FAC-KD0405', InvoiceStatus::Paid, 480_000,
            issuedAt: '2026-03-14', dueAt: '2026-04-13', paidAt: '2026-03-24',
            lines: [
                ['Développement module Suivi & Évaluation (M&E)', 1, 380_000],
                ['Formation équipe terrain (2 jours)', 2, 50_000],
            ]
        );

        $this->invoice($wari, 'FYK-FAC-KD0406', InvoiceStatus::Paid, 900_000,
            issuedAt: '2026-03-18', dueAt: '2026-04-17', paidAt: '2026-03-28',
            lines: [
                ['Optimisation architecture microservices Wari Pay', 1, 700_000],
                ['Revue de code et documentation technique', 1, 200_000],
            ]
        );

        // ── Factures impayées en retard ────────────────────────────────────────

        // Critique J+65 — LafargeHolcim
        $facLafarge = $this->invoice($lafarge, 'FYK-FAC-KD0501', InvoiceStatus::Overdue, 2_800_000,
            issuedAt: '2026-01-25', dueAt: '2026-02-05', paidAt: null,
            lines: [
                ['Migration ERP Sage X3 — Phase 2 : recettage & déploiement', 1, 2_200_000],
                ['Formation utilisateurs clés (8 jours)', 8, 75_000],
            ]
        );

        Reminder::create([
            'invoice_id' => $facLafarge->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(40),
            'message_body' => 'Rappel de paiement : la facture FYK-FAC-KD0501 d\'un montant de 3 304 000 F CFA est échue depuis 15 jours. Merci de procéder au règlement.',
            'recipient_email' => 'dsi@lafarge.sn',
        ]);

        Reminder::create([
            'invoice_id' => $facLafarge->id,
            'channel' => ReminderChannel::WhatsApp,
            'mode' => 'auto',
            'sent_at' => now()->subDays(25),
            'message_body' => 'Bonjour, nous revenons vers vous au sujet de la facture FYK-FAC-KD0501 de 3 304 000 F CFA, impayée depuis 30 jours. Pourriez-vous nous indiquer la date prévisionnelle de règlement ?',
            'recipient_phone' => '+221338600007',
        ]);

        Reminder::create([
            'invoice_id' => $facLafarge->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'manual',
            'sent_at' => now()->subDays(10),
            'message_body' => 'Mise en demeure amiable : la facture FYK-FAC-KD0501 reste impayée depuis 45 jours. Sans retour de votre part sous 7 jours, nous serons contraints d\'engager une procédure de recouvrement.',
            'recipient_email' => 'dsi@lafarge.sn',
        ]);

        Reminder::create([
            'invoice_id' => $facLafarge->id,
            'channel' => ReminderChannel::Sms,
            'mode' => 'manual',
            'sent_at' => null,
            'message_body' => 'KAAY DIGITAL : facture KD0501 de 3 304 000 F impayée depuis 65 jours. Contactez-nous au +221776200001.',
            'recipient_phone' => '+221338600007',
        ]);

        // Critique J+61 — SENELEC
        $facSenelec = $this->invoice($senelec, 'FYK-FAC-KD0502', InvoiceStatus::Overdue, 680_000,
            issuedAt: '2026-02-15', dueAt: '2026-02-09', paidAt: null,
            lines: [
                ['Maintenance applicative Q1 2026 — portail client SENELEC', 1, 520_000],
                ['Correction de 4 anomalies critiques (niveau P1)', 4, 40_000],
            ]
        );

        Reminder::create([
            'invoice_id' => $facSenelec->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(25),
            'message_body' => 'Rappel de paiement : la facture FYK-FAC-KD0502 de 802 400 F CFA est échue depuis 13 jours.',
            'recipient_email' => 'it@senelec.sn',
        ]);

        Reminder::create([
            'invoice_id' => $facSenelec->id,
            'channel' => ReminderChannel::WhatsApp,
            'mode' => 'manual',
            'sent_at' => now()->subDays(10),
            'message_body' => 'Bonjour, votre facture FYK-FAC-KD0502 de 802 400 F CFA est toujours en attente de règlement. Merci de bien vouloir la traiter en priorité.',
            'recipient_phone' => '+221338600011',
        ]);

        // En retard J+35 — AUCHAN
        $facAuchan = $this->invoice($auchan, 'FYK-FAC-KD0503', InvoiceStatus::Overdue, 550_000,
            issuedAt: '2026-03-01', dueAt: '2026-03-07', paidAt: null,
            lines: [
                ['Dashboard reporting ventes & stocks AUCHAN', 1, 420_000],
                ['Intégration flux EDI fournisseurs (3 fournisseurs)', 3, 43_333],
            ]
        );

        Reminder::create([
            'invoice_id' => $facAuchan->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(10),
            'message_body' => 'Rappel de paiement : la facture FYK-FAC-KD0503 de 649 000 F CFA est échue depuis 12 jours. Nous vous remercions de bien vouloir régulariser cette situation.',
            'recipient_email' => 'dsi@auchan.sn',
        ]);

        // Partiellement payée — TotalEnergies
        $facTotal = $this->invoice($totalEnergies, 'FYK-FAC-KD0504', InvoiceStatus::PartiallyPaid, 1_600_000,
            issuedAt: '2026-02-25', dueAt: '2026-03-27', paidAt: null,
            lines: [
                ['Conseil en transformation digitale TotalEnergies Sénégal', 1, 1_200_000],
                ['Audit des processus opérationnels (5 sites)', 5, 80_000],
            ],
            amountPaid: 800_000,
        );

        Reminder::create([
            'invoice_id' => $facTotal->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(8),
            'message_body' => 'Rappel : la facture FYK-FAC-KD0504 a été partiellement réglée (800 000 F). Le solde restant de 1 088 800 F CFA est échu depuis 10 jours. Merci de compléter le paiement.',
            'recipient_email' => 'compta@totalenergies.sn',
        ]);

        // ── Avril 2026 — 5 factures envoyées (à encaisser) ────────────────────
        $this->invoice($orange, 'FYK-FAC-KD0601', InvoiceStatus::Sent, 1_500_000,
            issuedAt: '2026-03-25', dueAt: '2026-04-24', paidAt: null,
            lines: [
                ['Contrat de maintenance applicative annuel — Application Orange Money', 1, 1_200_000],
                ['Astreinte et support niveau 2 (12 mois)', 12, 25_000],
            ]
        );

        $this->invoice($cbao, 'FYK-FAC-KD0602', InvoiceStatus::Sent, 1_200_000,
            issuedAt: '2026-03-28', dueAt: '2026-04-27', paidAt: null,
            lines: [
                ['Audit de sécurité du système d\'information CBAO', 1, 950_000],
                ['Tests d\'intrusion — applications critiques', 1, 250_000],
            ]
        );

        $this->invoice($adie, 'FYK-FAC-KD0603', InvoiceStatus::Sent, 2_200_000,
            issuedAt: '2026-03-26', dueAt: '2026-04-25', paidAt: null,
            lines: [
                ['Développement portail citoyen e-Sénégal v2', 1, 1_800_000],
                ['Intégration avec les registres nationaux (ANSD, DGID)', 1, 400_000],
            ]
        );

        $this->invoice($airSenegal, 'FYK-FAC-KD0604', InvoiceStatus::Sent, 1_800_000,
            issuedAt: '2026-03-22', dueAt: '2026-04-21', paidAt: null,
            lines: [
                ['Module e-ticketing Air Sénégal (web + mobile)', 1, 1_500_000],
                ['Intégration système de distribution GDS (Amadeus)', 1, 300_000],
            ]
        );

        $this->invoice($kirene, 'FYK-FAC-KD0605', InvoiceStatus::Sent, 480_000,
            issuedAt: '2026-03-20', dueAt: '2026-04-19', paidAt: null,
            lines: [
                ['Refonte site web institutionnel Kirène (design + développement)', 1, 380_000],
                ['Optimisation SEO et intégration Google Analytics 4', 1, 100_000],
            ]
        );

        // ── Avril 2026 — 5 factures émises (échéances Mai & Juin) ─────────────
        $this->invoice($cbao, 'FYK-FAC-KD0606', InvoiceStatus::Sent, 1_400_000,
            issuedAt: '2026-04-02', dueAt: '2026-05-02', paidAt: null,
            lines: [
                ['Développement module Open Banking CBAO (API PSD2)', 1, 1_100_000],
                ['Documentation et certification sécurité (ISO 27001)', 1, 300_000],
            ]
        );

        $this->invoice($wari, 'FYK-FAC-KD0607', InvoiceStatus::Sent, 980_000,
            issuedAt: '2026-04-05', dueAt: '2026-05-05', paidAt: null,
            lines: [
                ['Refonte UI/UX de l\'application Wari Mobile', 1, 750_000],
                ['Tests utilisateurs et itérations (sprint 3)', 1, 230_000],
            ]
        );

        $this->invoice($orange, 'FYK-FAC-KD0608', InvoiceStatus::Sent, 1_600_000,
            issuedAt: '2026-04-09', dueAt: '2026-05-09', paidAt: null,
            lines: [
                ['Développement plateforme IoT pour compteurs intelligents Orange', 1, 1_300_000],
                ['Intégration avec le réseau 5G et API de gestion', 1, 300_000],
            ]
        );

        $this->invoice($adie, 'FYK-FAC-KD0609', InvoiceStatus::Sent, 2_200_000,
            issuedAt: '2026-04-01', dueAt: '2026-06-01', paidAt: null,
            lines: [
                ['Développement système de gestion des identités numériques nationales', 1, 1_800_000],
                ['Intégration biométrique et interopérabilité des registres', 1, 400_000],
            ]
        );

        $this->invoice($planInternational, 'FYK-FAC-KD0610', InvoiceStatus::Sent, 850_000,
            issuedAt: '2026-04-05', dueAt: '2026-06-04', paidAt: null,
            lines: [
                ['Plateforme de collecte de données terrain — 12 régions du Sénégal', 1, 650_000],
                ['Formation data stewards et tableau de bord de supervision', 1, 200_000],
            ]
        );

        // ── 2 factures en brouillon ────────────────────────────────────────────
        $this->invoice($totalEnergies, 'FYK-FAC-KD0701', InvoiceStatus::Draft, 3_500_000,
            issuedAt: now()->toDateString(), dueAt: now()->addDays(30)->toDateString(), paidAt: null,
            lines: [
                ['Migration infrastructure vers Microsoft Azure (12 sites)', 1, 2_800_000],
                ['Déploiement Active Directory & sécurisation identités', 1, 700_000],
            ]
        );

        $this->invoice($ecobank, 'FYK-FAC-KD0702', InvoiceStatus::Draft, 2_000_000,
            issuedAt: now()->toDateString(), dueAt: now()->addDays(30)->toDateString(), paidAt: null,
            lines: [
                ['Développement application mobile banking Ecobank v2', 1, 1_600_000],
                ['Mise en conformité RGPD et tests de sécurité', 1, 400_000],
            ]
        );
    }

    private function seedQuotes(): void
    {
        $orange = Client::where('company_id', $this->company->id)->where('name', 'Orange Sénégal SA')->first();
        $lafarge = Client::where('company_id', $this->company->id)->where('name', 'LafargeHolcim Sénégal')->first();
        $cbao = Client::where('company_id', $this->company->id)->where('name', 'CBAO Groupe Attijariwafa')->first();
        $senelec = Client::where('company_id', $this->company->id)->where('name', 'SENELEC')->first();
        $airSenegal = Client::where('company_id', $this->company->id)->where('name', 'Air Sénégal SA')->first();
        $auchan = Client::where('company_id', $this->company->id)->where('name', 'AUCHAN Sénégal')->first();
        $totalEnergies = Client::where('company_id', $this->company->id)->where('name', 'TotalEnergies Marketing SN')->first();

        // Devis accepté — Orange (il y a 18 jours)
        $this->quote($orange, 'FYK-DEV-KD0101', QuoteStatus::Accepted, 2_400_000,
            issuedAt: now()->subDays(18)->toDateString(),
            validUntil: now()->subDays(18)->addDays(30)->toDateString(),
            lines: [
                ['Développement super-app Orange Sénégal (paiement, services, fidélité)', 1, 2_000_000],
                ['Architecture technique et sécurité', 1, 400_000],
            ]
        );

        // Devis accepté — LafargeHolcim (il y a 25 jours)
        $this->quote($lafarge, 'FYK-DEV-KD0102', QuoteStatus::Accepted, 1_950_000,
            issuedAt: now()->subDays(25)->toDateString(),
            validUntil: now()->subDays(25)->addDays(30)->toDateString(),
            lines: [
                ['Migration ERP Sage X3 — Phase 3 : optimisation & reporting avancé', 1, 1_600_000],
                ['Tableau de bord exécutif (C-Level)', 1, 350_000],
            ]
        );

        // Devis envoyé — CBAO (il y a 6 jours)
        $this->quote($cbao, 'FYK-DEV-KD0201', QuoteStatus::Sent, 3_200_000,
            issuedAt: now()->subDays(6)->toDateString(),
            validUntil: now()->subDays(6)->addDays(30)->toDateString(),
            lines: [
                ['Développement plateforme de banque digitale CBAO (web + mobile)', 1, 2_600_000],
                ['Intégration avec les systèmes de paiement régionaux UEMOA', 1, 600_000],
            ]
        );

        // Devis envoyé — SENELEC (il y a 9 jours)
        $this->quote($senelec, 'FYK-DEV-KD0202', QuoteStatus::Sent, 1_750_000,
            issuedAt: now()->subDays(9)->toDateString(),
            validUntil: now()->subDays(9)->addDays(30)->toDateString(),
            lines: [
                ['Système de gestion intelligente du réseau électrique (smart grid)', 1, 1_400_000],
                ['Tableaux de bord temps réel et alertes automatiques', 1, 350_000],
            ]
        );

        // Devis envoyé — Air Sénégal (il y a 4 jours)
        $this->quote($airSenegal, 'FYK-DEV-KD0203', QuoteStatus::Sent, 2_800_000,
            issuedAt: now()->subDays(4)->toDateString(),
            validUntil: now()->subDays(4)->addDays(30)->toDateString(),
            lines: [
                ['Plateforme de gestion des opérations aériennes (OCC)', 1, 2_200_000],
                ['Module de planification des équipages et des rotations', 1, 600_000],
            ]
        );

        // Devis refusé — AUCHAN (il y a 14 jours)
        $this->quote($auchan, 'FYK-DEV-KD0301', QuoteStatus::Declined, 4_500_000,
            issuedAt: now()->subDays(14)->toDateString(),
            validUntil: now()->subDays(14)->addDays(30)->toDateString(),
            lines: [
                ['Déploiement ERP Microsoft Dynamics 365 (distribution & retail)', 1, 3_800_000],
                ['Formation et accompagnement au changement (30 jours)', 30, 23_333],
            ]
        );

        // Devis expiré — TotalEnergies (il y a 45 jours)
        $this->quote($totalEnergies, 'FYK-DEV-KD0302', QuoteStatus::Expired,
            2_100_000,
            issuedAt: now()->subDays(45)->toDateString(),
            validUntil: now()->subDays(45)->addDays(30)->toDateString(),
            lines: [
                ['Développement plateforme HSE (Hygiène, Sécurité, Environnement)', 1, 1_700_000],
                ['Application mobile terrain inspecteurs', 1, 400_000],
            ]
        );
    }

    private function client(
        string $name,
        string $phone,
        string $email,
        string $address,
        string $taxId,
    ): Client {
        return Client::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'tax_id' => $taxId,
        ]);
    }

    /**
     * @param  array<int, array{string, int, int}>  $lines  [description, quantity, unit_price]
     */
    private function invoice(
        Client $client,
        string $reference,
        InvoiceStatus $status,
        int $subtotal,
        string $issuedAt,
        string $dueAt,
        ?string $paidAt,
        array $lines = [],
        int $amountPaid = 0,
    ): Invoice {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        if ($status === InvoiceStatus::Paid) {
            $amountPaid = $total;
        }

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'paid_at' => $paidAt,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'amount_paid' => $amountPaid,
        ]);

        foreach ($lines as [$description, $quantity, $unitPrice]) {
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $invoice;
    }

    /**
     * @param  array<int, array{string, int, int}>  $lines  [description, quantity, unit_price]
     */
    private function quote(
        Client $client,
        string $reference,
        QuoteStatus $status,
        int $subtotal,
        string $issuedAt,
        string $validUntil,
        array $lines = [],
    ): Quote {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        $quote = Quote::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'valid_until' => $validUntil,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        foreach ($lines as [$description, $quantity, $unitPrice]) {
            QuoteLine::create([
                'quote_id' => $quote->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $quote;
    }
}
