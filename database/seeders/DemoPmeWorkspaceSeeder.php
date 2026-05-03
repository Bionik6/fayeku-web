<?php

namespace Database\Seeders;

use App\Enums\PME\InvoiceStatus;
use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Enums\PME\ReminderChannel;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use App\Models\PME\ProposalDocument;
use App\Models\PME\ProposalDocumentLine;
use App\Models\PME\Reminder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Étoffe les deux PME nominales créées par DemoAccountsSeeder avec des
 * espaces de travail riches et exploitables :
 *
 * - Diop Services SARL (services numériques) — 12 clients (Orange, CBAO,
 *   SENELEC…), 5 mois d'historique, 4 retards (dont 2 critiques), 10 émises
 *   en attente, 2 brouillons, 7 devis variés et 6 proformas (envoyées,
 *   PO reçus, converties, refusées, expirées, brouillon).
 * - Sow BTP SARL (BTP / promotion immobilière) — 8 clients (promoteurs,
 *   syndics, collectivités), 4 mois d'historique, 3 retards (dont 1
 *   critique J+72), 4 émises, 1 brouillon, 4 devis et 5 proformas
 *   (envoyée, PO reçu, convertie, refusée, brouillon).
 *
 * Permet de valider tous les écrans PME (dashboard, factures, devis,
 * proformas, relances, trésorerie) sur deux profils sectoriels distincts.
 */
class DemoPmeWorkspaceSeeder extends Seeder
{
    private Company $company;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedDiopServicesWorkspace();
            $this->seedSowBtpWorkspace();
        });
    }

    private function seedDiopServicesWorkspace(): void
    {
        $this->company = Company::query()
            ->where('type', 'sme')
            ->where('name', 'Diop Services SARL')
            ->firstOrFail();

        $this->seedDiopInvoices();
        $this->seedDiopQuotes();
        $this->seedDiopProformas();
    }

    private function seedSowBtpWorkspace(): void
    {
        $this->company = Company::query()
            ->where('type', 'sme')
            ->where('name', 'Sow BTP SARL')
            ->firstOrFail();

        $this->seedSowBtpInvoices();
        $this->seedSowBtpQuotes();
        $this->seedSowBtpProformas();
    }

    private function seedDiopInvoices(): void
    {
        // ── Clients ────────────────────────────────────────────────────────
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

        // ── Décembre 2025 — 4 factures payées ─────────────────────────────
        $this->invoice($orange, 'FYK-FAC-DS0101', InvoiceStatus::Paid, 1_200_000,
            issuedAt: '2025-12-03', dueAt: '2026-01-02', paidAt: '2025-12-28',
            lines: [
                ['Développement application mobile Orange Money (iOS & Android)', 1, 950_000],
                ['Tests et assurance qualité', 1, 250_000],
            ]
        );

        $this->invoice($ecobank, 'FYK-FAC-DS0102', InvoiceStatus::Paid, 1_800_000,
            issuedAt: '2025-12-08', dueAt: '2026-01-07', paidAt: '2026-01-08',
            lines: [
                ['Audit sécurité du système d\'information bancaire', 1, 1_400_000],
                ['Rapport d\'audit et plan de remédiation', 1, 400_000],
            ]
        );

        $this->invoice($adie, 'FYK-FAC-DS0103', InvoiceStatus::Paid, 850_000,
            issuedAt: '2025-12-12', dueAt: '2026-01-11', paidAt: '2025-12-30',
            lines: [
                ['Formation cloud AWS — 10 jours (20 agents)', 10, 75_000],
                ['Support post-formation (1 mois)', 1, 100_000],
            ]
        );

        $this->invoice($wari, 'FYK-FAC-DS0104', InvoiceStatus::Paid, 650_000,
            issuedAt: '2025-12-20', dueAt: '2026-01-19', paidAt: '2026-01-03',
            lines: [
                ['Intégration API de paiement mobile (Wave & Orange Money)', 1, 500_000],
                ['Documentation technique et tests d\'intégration', 1, 150_000],
            ]
        );

        // ── Janvier 2026 — 5 factures payées ──────────────────────────────
        $this->invoice($orange, 'FYK-FAC-DS0201', InvoiceStatus::Paid, 420_000,
            issuedAt: '2026-01-05', dueAt: '2026-02-04', paidAt: '2026-02-05',
            lines: [
                ['Maintenance corrective plateforme Orange Money — Janv.', 1, 320_000],
                ['Support technique prioritaire (20 tickets)', 20, 5_000],
            ]
        );

        $this->invoice($cbao, 'FYK-FAC-DS0202', InvoiceStatus::Paid, 1_500_000,
            issuedAt: '2026-01-10', dueAt: '2026-02-09', paidAt: '2026-02-10',
            lines: [
                ['Développement module CRM Clientèle CBAO', 1, 1_200_000],
                ['Intégration avec core banking Oracle FLEXCUBE', 1, 300_000],
            ]
        );

        $this->invoice($planInternational, 'FYK-FAC-DS0203', InvoiceStatus::Paid, 980_000,
            issuedAt: '2026-01-14', dueAt: '2026-02-13', paidAt: '2026-02-15',
            lines: [
                ['Développement portail de gestion de projets terrain', 1, 750_000],
                ['Formation équipe (3 jours)', 3, 80_000],
                ['Hébergement et maintenance (6 mois)', 1, 80_000],
            ]
        );

        $this->invoice($totalEnergies, 'FYK-FAC-DS0204', InvoiceStatus::Paid, 720_000,
            issuedAt: '2026-01-18', dueAt: '2026-02-17', paidAt: '2026-02-12',
            lines: [
                ['Développement tableau de bord BI (Power BI + connecteurs)', 1, 580_000],
                ['Déploiement et formation administrateurs (2 jours)', 2, 70_000],
            ]
        );

        $this->invoice($lafarge, 'FYK-FAC-DS0205', InvoiceStatus::Paid, 2_100_000,
            issuedAt: '2026-01-22', dueAt: '2026-02-21', paidAt: '2026-02-20',
            lines: [
                ['Migration ERP Sage X3 — Phase 1 : analyse & paramétrage', 1, 1_600_000],
                ['Migration des données historiques (5 ans)', 1, 500_000],
            ]
        );

        // ── Février 2026 — 4 factures payées ──────────────────────────────
        $this->invoice($adie, 'FYK-FAC-DS0301', InvoiceStatus::Paid, 1_400_000,
            issuedAt: '2026-02-03', dueAt: '2026-03-05', paidAt: '2026-03-10',
            lines: [
                ['Refonte du dashboard SIGTAS (Système de Gestion Fiscale)', 1, 1_100_000],
                ['Tests fonctionnels et recettage métier', 1, 300_000],
            ]
        );

        $this->invoice($auchan, 'FYK-FAC-DS0302', InvoiceStatus::Paid, 960_000,
            issuedAt: '2026-02-10', dueAt: '2026-03-12', paidAt: '2026-03-08',
            lines: [
                ['Application mobile programme de fidélité AUCHAN', 1, 780_000],
                ['Backend API + tableau de bord administrateur', 1, 180_000],
            ]
        );

        $this->invoice($kirene, 'FYK-FAC-DS0303', InvoiceStatus::Paid, 750_000,
            issuedAt: '2026-02-14', dueAt: '2026-03-16', paidAt: '2026-03-05',
            lines: [
                ['Développement plateforme e-commerce B2B Kirène', 1, 600_000],
                ['Intégration paiement en ligne et livraison', 1, 150_000],
            ]
        );

        $this->invoice($senelec, 'FYK-FAC-DS0304', InvoiceStatus::Paid, 1_100_000,
            issuedAt: '2026-02-20', dueAt: '2026-03-22', paidAt: '2026-03-18',
            lines: [
                ['Refonte portail client SENELEC (web + mobile)', 1, 850_000],
                ['Module de suivi consommation en temps réel', 1, 250_000],
            ]
        );

        // ── Mars 2026 — 6 factures payées ─────────────────────────────────
        $this->invoice($orange, 'FYK-FAC-DS0401', InvoiceStatus::Paid, 1_800_000,
            issuedAt: '2026-03-02', dueAt: '2026-04-01', paidAt: '2026-03-12',
            lines: [
                ['Déploiement infrastructure réseau Orange — Phase 2', 1, 1_400_000],
                ['Supervision et monitoring (Zabbix + Grafana)', 1, 400_000],
            ]
        );

        $this->invoice($cbao, 'FYK-FAC-DS0402', InvoiceStatus::Paid, 2_500_000,
            issuedAt: '2026-03-05', dueAt: '2026-04-04', paidAt: '2026-03-20',
            lines: [
                ['Intégration core banking — Module virements SEPA', 1, 2_000_000],
                ['Tests de charge et sécurité (PCI-DSS)', 1, 500_000],
            ]
        );

        $this->invoice($airSenegal, 'FYK-FAC-DS0403', InvoiceStatus::Paid, 1_300_000,
            issuedAt: '2026-03-08', dueAt: '2026-04-07', paidAt: '2026-03-15',
            lines: [
                ['Application de gestion des vols internes', 1, 1_000_000],
                ['Module de gestion des équipages', 1, 300_000],
            ]
        );

        $this->invoice($ecobank, 'FYK-FAC-DS0404', InvoiceStatus::Paid, 620_000,
            issuedAt: '2026-03-11', dueAt: '2026-04-10', paidAt: '2026-03-22',
            lines: [
                ['Formation cybersécurité — 15 collaborateurs (3 jours)', 3, 150_000],
                ['Exercice de simulation d\'intrusion (pentest)', 1, 170_000],
            ]
        );

        $this->invoice($planInternational, 'FYK-FAC-DS0405', InvoiceStatus::Paid, 480_000,
            issuedAt: '2026-03-14', dueAt: '2026-04-13', paidAt: '2026-03-24',
            lines: [
                ['Module Suivi & Évaluation (M&E)', 1, 380_000],
                ['Formation équipe terrain (2 jours)', 2, 50_000],
            ]
        );

        $this->invoice($wari, 'FYK-FAC-DS0406', InvoiceStatus::Paid, 900_000,
            issuedAt: '2026-03-18', dueAt: '2026-04-17', paidAt: '2026-03-28',
            lines: [
                ['Optimisation architecture microservices Wari Pay', 1, 700_000],
                ['Revue de code et documentation technique', 1, 200_000],
            ]
        );

        // ── Factures impayées en retard ────────────────────────────────────

        // Critique J+65 — LafargeHolcim
        $facLafarge = $this->invoice($lafarge, 'FYK-FAC-DS0501', InvoiceStatus::Overdue, 2_800_000,
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
            'message_body' => 'Rappel de paiement : la facture FYK-FAC-DS0501 d\'un montant de 3 304 000 F CFA est échue depuis 15 jours. Merci de procéder au règlement.',
            'recipient_email' => 'dsi@lafarge.sn',
        ]);
        Reminder::create([
            'invoice_id' => $facLafarge->id,
            'channel' => ReminderChannel::WhatsApp,
            'mode' => 'auto',
            'sent_at' => now()->subDays(25),
            'message_body' => 'Bonjour, nous revenons vers vous au sujet de la facture FYK-FAC-DS0501 de 3 304 000 F CFA, impayée depuis 30 jours.',
            'recipient_phone' => '+221338600007',
        ]);
        Reminder::create([
            'invoice_id' => $facLafarge->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'manual',
            'sent_at' => now()->subDays(10),
            'message_body' => 'Mise en demeure amiable : la facture FYK-FAC-DS0501 reste impayée depuis 45 jours.',
            'recipient_email' => 'dsi@lafarge.sn',
        ]);
        Reminder::create([
            'invoice_id' => $facLafarge->id,
            'channel' => ReminderChannel::Sms,
            'mode' => 'manual',
            'sent_at' => null,
            'message_body' => 'DIOP SERVICES : facture DS0501 de 3 304 000 F impayée depuis 65 jours. Contactez-nous au +221774457633.',
            'recipient_phone' => '+221338600007',
        ]);

        // Critique J+61 — SENELEC
        $facSenelec = $this->invoice($senelec, 'FYK-FAC-DS0502', InvoiceStatus::Overdue, 680_000,
            issuedAt: '2026-02-15', dueAt: '2026-02-09', paidAt: null,
            lines: [
                ['Maintenance applicative Q1 2026 — portail client SENELEC', 1, 520_000],
                ['Correction de 4 anomalies critiques (P1)', 4, 40_000],
            ]
        );
        Reminder::create([
            'invoice_id' => $facSenelec->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(25),
            'message_body' => 'Rappel de paiement : la facture FYK-FAC-DS0502 de 802 400 F CFA est échue depuis 13 jours.',
            'recipient_email' => 'it@senelec.sn',
        ]);
        Reminder::create([
            'invoice_id' => $facSenelec->id,
            'channel' => ReminderChannel::WhatsApp,
            'mode' => 'manual',
            'sent_at' => now()->subDays(10),
            'message_body' => 'Bonjour, votre facture FYK-FAC-DS0502 de 802 400 F CFA est toujours en attente.',
            'recipient_phone' => '+221338600011',
        ]);

        // En retard J+35 — AUCHAN
        $facAuchan = $this->invoice($auchan, 'FYK-FAC-DS0503', InvoiceStatus::Overdue, 550_000,
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
            'message_body' => 'Rappel : la facture FYK-FAC-DS0503 de 649 000 F CFA est échue depuis 12 jours.',
            'recipient_email' => 'dsi@auchan.sn',
        ]);

        // Partiellement payée — TotalEnergies
        $facTotal = $this->invoice($totalEnergies, 'FYK-FAC-DS0504', InvoiceStatus::PartiallyPaid, 1_600_000,
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
            'message_body' => 'Rappel : la facture FYK-FAC-DS0504 a été partiellement réglée (800 000 F). Solde 1 088 800 F CFA échu depuis 10 jours.',
            'recipient_email' => 'compta@totalenergies.sn',
        ]);

        // ── Avril 2026 — 5 factures envoyées (à encaisser) ────────────────
        $this->invoice($orange, 'FYK-FAC-DS0601', InvoiceStatus::Sent, 1_500_000,
            issuedAt: '2026-03-25', dueAt: '2026-04-24', paidAt: null,
            lines: [
                ['Contrat de maintenance applicative annuel — Orange Money', 1, 1_200_000],
                ['Astreinte et support niveau 2 (12 mois)', 12, 25_000],
            ]
        );

        $this->invoice($cbao, 'FYK-FAC-DS0602', InvoiceStatus::Sent, 1_200_000,
            issuedAt: '2026-03-28', dueAt: '2026-04-27', paidAt: null,
            lines: [
                ['Audit de sécurité du système d\'information CBAO', 1, 950_000],
                ['Tests d\'intrusion — applications critiques', 1, 250_000],
            ]
        );

        $this->invoice($adie, 'FYK-FAC-DS0603', InvoiceStatus::Sent, 2_200_000,
            issuedAt: '2026-03-26', dueAt: '2026-04-25', paidAt: null,
            lines: [
                ['Développement portail citoyen e-Sénégal v2', 1, 1_800_000],
                ['Intégration avec les registres nationaux (ANSD, DGID)', 1, 400_000],
            ]
        );

        $this->invoice($airSenegal, 'FYK-FAC-DS0604', InvoiceStatus::Sent, 1_800_000,
            issuedAt: '2026-03-22', dueAt: '2026-04-21', paidAt: null,
            lines: [
                ['Module e-ticketing Air Sénégal (web + mobile)', 1, 1_500_000],
                ['Intégration GDS Amadeus', 1, 300_000],
            ]
        );

        $this->invoice($kirene, 'FYK-FAC-DS0605', InvoiceStatus::Sent, 480_000,
            issuedAt: '2026-03-20', dueAt: '2026-04-19', paidAt: null,
            lines: [
                ['Refonte site web institutionnel Kirène', 1, 380_000],
                ['Optimisation SEO et intégration GA4', 1, 100_000],
            ]
        );

        // ── Avril 2026 — 5 factures émises (échéances Mai & Juin) ─────────
        $this->invoice($cbao, 'FYK-FAC-DS0606', InvoiceStatus::Sent, 1_400_000,
            issuedAt: '2026-04-02', dueAt: '2026-05-02', paidAt: null,
            lines: [
                ['Module Open Banking CBAO (API PSD2)', 1, 1_100_000],
                ['Documentation et certification ISO 27001', 1, 300_000],
            ]
        );

        $this->invoice($wari, 'FYK-FAC-DS0607', InvoiceStatus::Sent, 980_000,
            issuedAt: '2026-04-05', dueAt: '2026-05-05', paidAt: null,
            lines: [
                ['Refonte UI/UX de l\'application Wari Mobile', 1, 750_000],
                ['Tests utilisateurs et itérations (sprint 3)', 1, 230_000],
            ]
        );

        $this->invoice($orange, 'FYK-FAC-DS0608', InvoiceStatus::Sent, 1_600_000,
            issuedAt: '2026-04-09', dueAt: '2026-05-09', paidAt: null,
            lines: [
                ['Plateforme IoT pour compteurs intelligents Orange', 1, 1_300_000],
                ['Intégration avec le réseau 5G et API de gestion', 1, 300_000],
            ]
        );

        $this->invoice($adie, 'FYK-FAC-DS0609', InvoiceStatus::Sent, 2_200_000,
            issuedAt: '2026-04-01', dueAt: '2026-06-01', paidAt: null,
            lines: [
                ['Système de gestion des identités numériques nationales', 1, 1_800_000],
                ['Intégration biométrique et interopérabilité', 1, 400_000],
            ]
        );

        $this->invoice($planInternational, 'FYK-FAC-DS0610', InvoiceStatus::Sent, 850_000,
            issuedAt: '2026-04-05', dueAt: '2026-06-04', paidAt: null,
            lines: [
                ['Plateforme de collecte de données terrain — 12 régions', 1, 650_000],
                ['Formation data stewards et tableau de bord', 1, 200_000],
            ]
        );

        // ── 2 factures en brouillon ────────────────────────────────────────
        $this->invoice($totalEnergies, 'FYK-FAC-DS0701', InvoiceStatus::Draft, 3_500_000,
            issuedAt: now()->toDateString(), dueAt: now()->addDays(30)->toDateString(), paidAt: null,
            lines: [
                ['Migration infrastructure vers Microsoft Azure (12 sites)', 1, 2_800_000],
                ['Active Directory & sécurisation identités', 1, 700_000],
            ]
        );

        $this->invoice($ecobank, 'FYK-FAC-DS0702', InvoiceStatus::Draft, 2_000_000,
            issuedAt: now()->toDateString(), dueAt: now()->addDays(30)->toDateString(), paidAt: null,
            lines: [
                ['Application mobile banking Ecobank v2', 1, 1_600_000],
                ['Mise en conformité RGPD et tests de sécurité', 1, 400_000],
            ]
        );
    }

    private function seedDiopQuotes(): void
    {
        $orange = Client::where('company_id', $this->company->id)->where('name', 'Orange Sénégal SA')->first();
        $lafarge = Client::where('company_id', $this->company->id)->where('name', 'LafargeHolcim Sénégal')->first();
        $cbao = Client::where('company_id', $this->company->id)->where('name', 'CBAO Groupe Attijariwafa')->first();
        $senelec = Client::where('company_id', $this->company->id)->where('name', 'SENELEC')->first();
        $airSenegal = Client::where('company_id', $this->company->id)->where('name', 'Air Sénégal SA')->first();
        $auchan = Client::where('company_id', $this->company->id)->where('name', 'AUCHAN Sénégal')->first();
        $totalEnergies = Client::where('company_id', $this->company->id)->where('name', 'TotalEnergies Marketing SN')->first();

        $this->quote($orange, 'FYK-DEV-DS0101', ProposalDocumentStatus::Accepted, 2_400_000,
            issuedAt: now()->subDays(18)->toDateString(),
            validUntil: now()->subDays(18)->addDays(30)->toDateString(),
            lines: [
                ['Super-app Orange Sénégal (paiement, services, fidélité)', 1, 2_000_000],
                ['Architecture technique et sécurité', 1, 400_000],
            ]
        );

        $this->quote($lafarge, 'FYK-DEV-DS0102', ProposalDocumentStatus::Accepted, 1_950_000,
            issuedAt: now()->subDays(25)->toDateString(),
            validUntil: now()->subDays(25)->addDays(30)->toDateString(),
            lines: [
                ['Migration ERP Sage X3 — Phase 3 : optimisation & reporting', 1, 1_600_000],
                ['Tableau de bord exécutif', 1, 350_000],
            ]
        );

        $this->quote($cbao, 'FYK-DEV-DS0201', ProposalDocumentStatus::Sent, 3_200_000,
            issuedAt: now()->subDays(6)->toDateString(),
            validUntil: now()->subDays(6)->addDays(30)->toDateString(),
            lines: [
                ['Plateforme de banque digitale CBAO (web + mobile)', 1, 2_600_000],
                ['Intégration paiement régional UEMOA', 1, 600_000],
            ]
        );

        $this->quote($senelec, 'FYK-DEV-DS0202', ProposalDocumentStatus::Sent, 1_750_000,
            issuedAt: now()->subDays(9)->toDateString(),
            validUntil: now()->subDays(9)->addDays(30)->toDateString(),
            lines: [
                ['Smart grid SENELEC', 1, 1_400_000],
                ['Tableaux de bord temps réel et alertes', 1, 350_000],
            ]
        );

        $this->quote($airSenegal, 'FYK-DEV-DS0203', ProposalDocumentStatus::Sent, 2_800_000,
            issuedAt: now()->subDays(4)->toDateString(),
            validUntil: now()->subDays(4)->addDays(30)->toDateString(),
            lines: [
                ['Plateforme de gestion des opérations aériennes (OCC)', 1, 2_200_000],
                ['Module de planification équipages et rotations', 1, 600_000],
            ]
        );

        $this->quote($auchan, 'FYK-DEV-DS0301', ProposalDocumentStatus::Declined, 4_500_000,
            issuedAt: now()->subDays(14)->toDateString(),
            validUntil: now()->subDays(14)->addDays(30)->toDateString(),
            lines: [
                ['Déploiement ERP Microsoft Dynamics 365 (retail)', 1, 3_800_000],
                ['Formation et accompagnement (30 jours)', 30, 23_333],
            ]
        );

        $this->quote($totalEnergies, 'FYK-DEV-DS0302', ProposalDocumentStatus::Expired, 2_100_000,
            issuedAt: now()->subDays(45)->toDateString(),
            validUntil: now()->subDays(45)->addDays(30)->toDateString(),
            lines: [
                ['Plateforme HSE (Hygiène, Sécurité, Environnement)', 1, 1_700_000],
                ['Application mobile terrain inspecteurs', 1, 400_000],
            ]
        );
    }

    private function seedDiopProformas(): void
    {
        $orange = Client::where('company_id', $this->company->id)->where('name', 'Orange Sénégal SA')->first();
        $cbao = Client::where('company_id', $this->company->id)->where('name', 'CBAO Groupe Attijariwafa')->first();
        $adie = Client::where('company_id', $this->company->id)->where('name', 'Agence de l\'Informatique de l\'État')->first();
        $auchan = Client::where('company_id', $this->company->id)->where('name', 'AUCHAN Sénégal')->first();
        $airSenegal = Client::where('company_id', $this->company->id)->where('name', 'Air Sénégal SA')->first();
        $totalEnergies = Client::where('company_id', $this->company->id)->where('name', 'TotalEnergies Marketing SN')->first();

        // ── Envoyée — en attente de bon de commande ────────────────────────
        $this->proforma($orange, 'FYK-PRO-DS0101', ProposalDocumentStatus::Sent, 4_200_000,
            issuedAt: now()->subDays(7)->toDateString(),
            validUntil: now()->subDays(7)->addDays(45)->toDateString(),
            lines: [
                ['Conception architecture IA — détection de fraudes Orange Money', 1, 3_200_000],
                ['Modèles ML & infrastructure GPU (3 mois)', 1, 1_000_000],
            ],
            dossierReference: 'DOSS-2026-ORA-IA-001',
            paymentTerms: '30% à la commande, 40% à mi-parcours, 30% à la livraison',
            deliveryTerms: 'Démarrage sous 15 jours après réception du bon de commande',
        );

        // ── PO reçu — conversion en facture en cours ───────────────────────
        $this->proforma($cbao, 'FYK-PRO-DS0102', ProposalDocumentStatus::PoReceived, 5_800_000,
            issuedAt: now()->subDays(20)->toDateString(),
            validUntil: now()->subDays(20)->addDays(45)->toDateString(),
            lines: [
                ['Plateforme open banking CBAO — étude de cadrage et architecture', 1, 1_800_000],
                ['Développement MVP (3 mois — 4 développeurs)', 1, 4_000_000],
            ],
            dossierReference: 'DOSS-2026-CBAO-OB-002',
            paymentTerms: '40% à la commande, 60% à la livraison',
            deliveryTerms: '4 mois après bon de commande',
            poReference: 'CBAO-PO-2026-0048',
            poReceivedAt: now()->subDays(3)->toDateString(),
            poNotes: 'Bon de commande validé par la DSI le 30/04, démarrage prévu courant mai.',
        );

        // ── Convertie — facture associée créée ─────────────────────────────
        $proformaAdie = $this->proforma($adie, 'FYK-PRO-DS0103', ProposalDocumentStatus::Converted, 6_500_000,
            issuedAt: now()->subDays(50)->toDateString(),
            validUntil: now()->subDays(50)->addDays(45)->toDateString(),
            lines: [
                ['Refonte portail e-Sénégal — migration cloud souverain (DataCenter ADIE)', 1, 4_500_000],
                ['Sécurisation et certification ANSSI', 1, 2_000_000],
            ],
            dossierReference: 'DOSS-2026-ADIE-INFRA-003',
            paymentTerms: '50% à la commande, 50% à la livraison',
            deliveryTerms: '3 mois après bon de commande',
            poReference: 'ADIE-MP-2026-0124',
            poReceivedAt: now()->subDays(35)->toDateString(),
            poNotes: 'Marché public 0124 — notification le 28/03/2026.',
        );
        $this->invoice($adie, 'FYK-FAC-DS0801', InvoiceStatus::Paid, 6_500_000,
            issuedAt: now()->subDays(30)->toDateString(),
            dueAt: now()->subDays(30)->addDays(30)->toDateString(),
            paidAt: now()->subDays(8)->toDateString(),
            lines: [
                ['Refonte portail e-Sénégal — migration cloud souverain (DataCenter ADIE)', 1, 4_500_000],
                ['Sécurisation et certification ANSSI', 1, 2_000_000],
            ],
            proposalDocumentId: $proformaAdie->id,
        );

        // ── Refusée ────────────────────────────────────────────────────────
        $this->proforma($auchan, 'FYK-PRO-DS0104', ProposalDocumentStatus::Declined, 8_200_000,
            issuedAt: now()->subDays(40)->toDateString(),
            validUntil: now()->subDays(40)->addDays(45)->toDateString(),
            lines: [
                ['Plateforme SCM AUCHAN — modules achats, stocks, logistique', 1, 6_800_000],
                ['Intégration ERP existant et formation utilisateurs', 1, 1_400_000],
            ],
            dossierReference: 'DOSS-2026-AUCHAN-SCM-004',
            paymentTerms: '30% à la commande, 30% à mi-parcours, 40% à la livraison',
            deliveryTerms: '6 mois',
            notes: 'Budget non validé — projet reporté à l\'exercice suivant.',
        );

        // ── Expirée ────────────────────────────────────────────────────────
        $this->proforma($airSenegal, 'FYK-PRO-DS0105', ProposalDocumentStatus::Expired, 4_800_000,
            issuedAt: now()->subDays(70)->toDateString(),
            validUntil: now()->subDays(40)->toDateString(),
            lines: [
                ['Système de gestion des pièces de rechange aéronautique', 1, 3_900_000],
                ['Interfaçage avec maintenance MRO (catalogues OEM)', 1, 900_000],
            ],
            dossierReference: 'DOSS-2026-AIRSN-MRO-005',
            paymentTerms: '40% à la commande, 60% à la livraison',
            deliveryTerms: '4 mois',
        );

        // ── Brouillon — en cours de finalisation interne ───────────────────
        $this->proforma($totalEnergies, 'FYK-PRO-DS0106', ProposalDocumentStatus::Draft, 3_500_000,
            issuedAt: now()->toDateString(),
            validUntil: now()->addDays(45)->toDateString(),
            lines: [
                ['Plateforme HSE temps réel — IoT terrain (capteurs + dashboards)', 1, 2_700_000],
                ['Application mobile inspecteurs et workflows correctifs', 1, 800_000],
            ],
            dossierReference: 'DOSS-2026-TOTAL-HSE-006',
            paymentTerms: '30% à la commande, 70% à la recette finale',
            deliveryTerms: '5 mois',
            notes: 'En attente de validation du périmètre par la direction HSE.',
        );
    }

    private function seedSowBtpInvoices(): void
    {
        // ── Clients BTP / promotion immobilière ────────────────────────────
        $socofim = $this->client('SOCOFIM SA — Promotion Immobilière', '+221338700001', 'projets@socofim.sn', '12 Avenue Cheikh Anta Diop, Fann', 'SN2024SOC0001');
        $almadiesPlaza = $this->client('SCI Almadies Plaza', '+221338700002', 'syndic@almadiesplaza.sn', 'Route des Almadies, Lot 47, Dakar', 'SN2024ALM0002');
        $mairieDiamniadio = $this->client('Mairie de Diamniadio', '+221338700003', 'urbanisme@diamniadio.sn', 'Préfecture de Rufisque, Diamniadio', 'SN2024MAD0003');
        $terrouBi = $this->client('Hôtel Terrou-Bi', '+221338700004', 'travaux@terroubi.sn', 'Boulevard du Sud, Corniche Ouest', 'SN2024TBI0004');
        $senico = $this->client('SENICO Construction', '+221338700005', 'achats@senico.sn', 'Zone Industrielle Sébikhotane, Rufisque', 'SN2024SCO0005');
        $setuna = $this->client('SETUNA SA — Travaux Publics', '+221338700006', 'compta@setuna.sn', 'Km 5,5 Route de Rufisque, Hann', 'SN2024STU0006');
        $ucad = $this->client('Université Cheikh Anta Diop', '+221338700007', 'patrimoine@ucad.edu.sn', 'Avenue Cheikh Anta Diop, Fann', 'SN2024UCA0007');
        $poleDiamniadio = $this->client('Pôle Urbain de Diamniadio', '+221338700008', 'travaux@pud-diamniadio.sn', 'DGPU, Cité Diamniadio', 'SN2024PUD0008');

        // ── Janvier 2026 — 4 factures payées ──────────────────────────────
        $this->invoice($socofim, 'FYK-FAC-SB0101', InvoiceStatus::Paid, 8_500_000,
            issuedAt: '2026-01-08', dueAt: '2026-02-07', paidAt: '2026-02-02',
            lines: [
                ['Fondations spéciales — Résidence Les Jardins de Yoff (Tranche 1)', 1, 6_500_000],
                ['Études géotechniques et plan d\'exécution', 1, 1_400_000],
                ['Coordination SPS chantier (Janv. 2026)', 1, 600_000],
            ]
        );

        $this->invoice($almadiesPlaza, 'FYK-FAC-SB0102', InvoiceStatus::Paid, 1_800_000,
            issuedAt: '2026-01-12', dueAt: '2026-02-11', paidAt: '2026-02-10',
            lines: [
                ['Réfection étanchéité toiture-terrasse — Bâtiments A et B', 1, 1_500_000],
                ['Reprise garde-corps acier inox — 12 balcons', 12, 25_000],
            ]
        );

        $this->invoice($terrouBi, 'FYK-FAC-SB0103', InvoiceStatus::Paid, 3_200_000,
            issuedAt: '2026-01-18', dueAt: '2026-02-17', paidAt: '2026-02-15',
            lines: [
                ['Rénovation suites VIP (4 unités) — second œuvre complet', 4, 700_000],
                ['Plomberie sanitaire haut de gamme (Hansgrohe)', 4, 100_000],
            ]
        );

        $this->invoice($senico, 'FYK-FAC-SB0104', InvoiceStatus::Paid, 950_000,
            issuedAt: '2026-01-25', dueAt: '2026-02-24', paidAt: '2026-02-19',
            lines: [
                ['Sous-traitance maçonnerie — Chantier Cité Keur Gorgui (Janv.)', 1, 750_000],
                ['Location grue à tour — 5 jours', 5, 40_000],
            ]
        );

        // ── Février 2026 — 4 factures payées ──────────────────────────────
        $this->invoice($mairieDiamniadio, 'FYK-FAC-SB0201', InvoiceStatus::Paid, 4_200_000,
            issuedAt: '2026-02-03', dueAt: '2026-03-05', paidAt: '2026-03-08',
            lines: [
                ['Voirie et assainissement — Quartier Cité Mbaye (Phase 1)', 1, 3_400_000],
                ['Bordures, caniveaux et regards préfabriqués', 1, 800_000],
            ]
        );

        $this->invoice($ucad, 'FYK-FAC-SB0202', InvoiceStatus::Paid, 5_800_000,
            issuedAt: '2026-02-08', dueAt: '2026-03-10', paidAt: '2026-03-04',
            lines: [
                ['Extension Faculté des Sciences — gros œuvre R+2 (lot 1/3)', 1, 4_800_000],
                ['Charpente métallique préau d\'accueil (240 m²)', 240, 4_166],
            ]
        );

        $this->invoice($poleDiamniadio, 'FYK-FAC-SB0203', InvoiceStatus::Paid, 6_400_000,
            issuedAt: '2026-02-14', dueAt: '2026-03-16', paidAt: '2026-03-15',
            lines: [
                ['Aménagement parc urbain Diamniadio — terrassement et plantations', 1, 4_800_000],
                ['Mobilier urbain (40 bancs, 20 lampadaires solaires)', 1, 1_600_000],
            ]
        );

        $this->invoice($setuna, 'FYK-FAC-SB0204', InvoiceStatus::Paid, 1_350_000,
            issuedAt: '2026-02-22', dueAt: '2026-03-24', paidAt: '2026-03-12',
            lines: [
                ['Sous-traitance VRD — Chantier autoroute AIBD-Mbour (lot Sénégal)', 1, 1_200_000],
                ['Location matériel TP (compacteur, niveleuse)', 5, 30_000],
            ]
        );

        // ── Mars 2026 — 5 factures payées ─────────────────────────────────
        $this->invoice($socofim, 'FYK-FAC-SB0301', InvoiceStatus::Paid, 9_200_000,
            issuedAt: '2026-03-02', dueAt: '2026-04-01', paidAt: '2026-03-25',
            lines: [
                ['Gros œuvre R+5 — Résidence Les Jardins de Yoff (Tranche 2)', 1, 7_500_000],
                ['Élévation et planchers béton armé (1 200 m²)', 1_200, 1_416],
            ]
        );

        $this->invoice($almadiesPlaza, 'FYK-FAC-SB0302', InvoiceStatus::Paid, 780_000,
            issuedAt: '2026-03-06', dueAt: '2026-04-05', paidAt: '2026-04-02',
            lines: [
                ['Maintenance ascenseurs (2 batteries) — Q1 2026', 1, 480_000],
                ['Remplacement éclairage parties communes en LED', 1, 300_000],
            ]
        );

        $this->invoice($terrouBi, 'FYK-FAC-SB0303', InvoiceStatus::Paid, 2_100_000,
            issuedAt: '2026-03-10', dueAt: '2026-04-09', paidAt: '2026-04-04',
            lines: [
                ['Création SPA et hammam — second œuvre + équipements', 1, 1_700_000],
                ['Carrelage grès cérame haut de gamme (180 m²)', 180, 2_222],
            ]
        );

        $this->invoice($mairieDiamniadio, 'FYK-FAC-SB0304', InvoiceStatus::Paid, 3_500_000,
            issuedAt: '2026-03-15', dueAt: '2026-04-14', paidAt: '2026-04-08',
            lines: [
                ['Construction salle polyvalente municipale (lot 1 — gros œuvre)', 1, 2_900_000],
                ['Étude technique, BET structure et fluides', 1, 600_000],
            ]
        );

        $this->invoice($senico, 'FYK-FAC-SB0305', InvoiceStatus::Paid, 1_400_000,
            issuedAt: '2026-03-22', dueAt: '2026-04-21', paidAt: '2026-04-12',
            lines: [
                ['Sous-traitance second œuvre — Cité Keur Gorgui (Mars)', 1, 1_100_000],
                ['Reprise enduits et peinture extérieure (Bâtiment C)', 1, 300_000],
            ]
        );

        // ── Avril 2026 — 3 factures payées ────────────────────────────────
        $this->invoice($poleDiamniadio, 'FYK-FAC-SB0401', InvoiceStatus::Paid, 4_800_000,
            issuedAt: '2026-04-02', dueAt: '2026-05-02', paidAt: '2026-04-22',
            lines: [
                ['Réseau d\'éclairage public — Avenue centrale Diamniadio (1,8 km)', 1, 4_000_000],
                ['Coffrets de commande et raccordement SENELEC', 1, 800_000],
            ]
        );

        $this->invoice($setuna, 'FYK-FAC-SB0402', InvoiceStatus::Paid, 1_800_000,
            issuedAt: '2026-04-05', dueAt: '2026-05-05', paidAt: '2026-04-18',
            lines: [
                ['Sous-traitance terrassement — chantier Pôle Diamniadio Phase 4', 1, 1_500_000],
                ['Mise à disposition équipe pose de bordures (10 jours)', 10, 30_000],
            ]
        );

        $this->invoice($ucad, 'FYK-FAC-SB0403', InvoiceStatus::Paid, 2_650_000,
            issuedAt: '2026-04-08', dueAt: '2026-05-08', paidAt: '2026-04-20',
            lines: [
                ['Réhabilitation amphi 1000 — climatisation et acoustique', 1, 2_100_000],
                ['Sièges et estrade conférencier', 1, 550_000],
            ]
        );

        // ── Factures impayées ──────────────────────────────────────────────

        // Critique J+72 — Hôtel Terrou-Bi (gros chantier, paiement bloqué)
        $facTerrouBi = $this->invoice($terrouBi, 'FYK-FAC-SB0501', InvoiceStatus::Overdue, 4_500_000,
            issuedAt: '2026-01-20', dueAt: '2026-02-15', paidAt: null,
            lines: [
                ['Création piscine extérieure et plage immergée — gros œuvre', 1, 3_600_000],
                ['Étanchéité et carrelage piscine (90 m²)', 90, 10_000],
            ]
        );
        Reminder::create([
            'invoice_id' => $facTerrouBi->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(50),
            'message_body' => 'Rappel : la facture FYK-FAC-SB0501 d\'un montant de 5 310 000 F CFA est échue depuis 20 jours. Merci de procéder au règlement.',
            'recipient_email' => 'travaux@terroubi.sn',
        ]);
        Reminder::create([
            'invoice_id' => $facTerrouBi->id,
            'channel' => ReminderChannel::WhatsApp,
            'mode' => 'auto',
            'sent_at' => now()->subDays(30),
            'message_body' => 'Bonjour, votre facture FYK-FAC-SB0501 de 5 310 000 F CFA reste impayée depuis 40 jours.',
            'recipient_phone' => '+221338700004',
        ]);
        Reminder::create([
            'invoice_id' => $facTerrouBi->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'manual',
            'sent_at' => now()->subDays(15),
            'message_body' => 'Mise en demeure amiable : facture FYK-FAC-SB0501 impayée depuis 55 jours, merci de prendre contact rapidement.',
            'recipient_email' => 'travaux@terroubi.sn',
        ]);

        // J+38 — Université Cheikh Anta Diop (paiement administratif lent)
        $facUcad = $this->invoice($ucad, 'FYK-FAC-SB0502', InvoiceStatus::Overdue, 2_200_000,
            issuedAt: '2026-02-25', dueAt: '2026-03-20', paidAt: null,
            lines: [
                ['Étanchéité toiture amphi Khaly Amar — 320 m²', 320, 6_250],
                ['Mise aux normes parafoudre bâtiment principal', 1, 200_000],
            ]
        );
        Reminder::create([
            'invoice_id' => $facUcad->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(20),
            'message_body' => 'Rappel : la facture FYK-FAC-SB0502 de 2 596 000 F CFA est échue depuis 18 jours.',
            'recipient_email' => 'patrimoine@ucad.edu.sn',
        ]);

        // J+22 — Mairie Diamniadio (premier rappel uniquement)
        $facMairie = $this->invoice($mairieDiamniadio, 'FYK-FAC-SB0503', InvoiceStatus::Overdue, 1_650_000,
            issuedAt: '2026-03-15', dueAt: '2026-04-04', paidAt: null,
            lines: [
                ['Aménagement parking Mairie — 40 places + signalétique', 1, 1_350_000],
                ['Bornes anti-stationnement et marquage horizontal', 1, 300_000],
            ]
        );
        Reminder::create([
            'invoice_id' => $facMairie->id,
            'channel' => ReminderChannel::Email,
            'mode' => 'auto',
            'sent_at' => now()->subDays(7),
            'message_body' => 'Rappel : la facture FYK-FAC-SB0503 de 1 947 000 F CFA est échue depuis 14 jours.',
            'recipient_email' => 'urbanisme@diamniadio.sn',
        ]);

        // ── Avril 2026 — 4 factures émises (à encaisser) ──────────────────
        $this->invoice($socofim, 'FYK-FAC-SB0601', InvoiceStatus::Sent, 7_800_000,
            issuedAt: '2026-04-08', dueAt: '2026-05-08', paidAt: null,
            lines: [
                ['Gros œuvre R+5 — Résidence Les Jardins de Yoff (Tranche 3)', 1, 6_400_000],
                ['Cloisons et pré-cadres menuiseries (520 m²)', 520, 2_692],
            ]
        );

        $this->invoice($almadiesPlaza, 'FYK-FAC-SB0602', InvoiceStatus::Sent, 1_200_000,
            issuedAt: '2026-04-12', dueAt: '2026-05-12', paidAt: null,
            lines: [
                ['Réfection façade entrée principale — ravalement', 1, 950_000],
                ['Mise en peinture portail et garde-corps acier', 1, 250_000],
            ]
        );

        $this->invoice($poleDiamniadio, 'FYK-FAC-SB0603', InvoiceStatus::Sent, 5_400_000,
            issuedAt: '2026-04-15', dueAt: '2026-05-15', paidAt: null,
            lines: [
                ['Réseau eaux pluviales — Avenue centrale Diamniadio', 1, 4_500_000],
                ['Bassins de rétention et exutoires (4 unités)', 4, 225_000],
            ]
        );

        $this->invoice($senico, 'FYK-FAC-SB0604', InvoiceStatus::Sent, 1_650_000,
            issuedAt: '2026-04-18', dueAt: '2026-05-18', paidAt: null,
            lines: [
                ['Sous-traitance maçonnerie — Cité Keur Gorgui (Avril)', 1, 1_300_000],
                ['Reprise enduits façade Bâtiment D', 1, 350_000],
            ]
        );

        // ── 1 facture en brouillon ────────────────────────────────────────
        $this->invoice($mairieDiamniadio, 'FYK-FAC-SB0701', InvoiceStatus::Draft, 6_800_000,
            issuedAt: now()->toDateString(), dueAt: now()->addDays(30)->toDateString(), paidAt: null,
            lines: [
                ['Construction salle polyvalente municipale (lot 2 — second œuvre)', 1, 5_400_000],
                ['Lot menuiseries extérieures alu (12 ouvertures)', 12, 116_666],
            ]
        );
    }

    private function seedSowBtpQuotes(): void
    {
        $socofim = Client::where('company_id', $this->company->id)->where('name', 'SOCOFIM SA — Promotion Immobilière')->first();
        $terrouBi = Client::where('company_id', $this->company->id)->where('name', 'Hôtel Terrou-Bi')->first();
        $poleDiamniadio = Client::where('company_id', $this->company->id)->where('name', 'Pôle Urbain de Diamniadio')->first();
        $ucad = Client::where('company_id', $this->company->id)->where('name', 'Université Cheikh Anta Diop')->first();

        $this->quote($socofim, 'FYK-DEV-SB0101', ProposalDocumentStatus::Accepted, 12_500_000,
            issuedAt: now()->subDays(20)->toDateString(),
            validUntil: now()->subDays(20)->addDays(30)->toDateString(),
            lines: [
                ['Résidence Les Jardins de Yoff — Tranche 4 (R+5, 24 logements)', 1, 10_500_000],
                ['Maîtrise d\'œuvre d\'exécution et coordination SPS', 1, 2_000_000],
            ]
        );

        $this->quote($poleDiamniadio, 'FYK-DEV-SB0102', ProposalDocumentStatus::Sent, 9_200_000,
            issuedAt: now()->subDays(8)->toDateString(),
            validUntil: now()->subDays(8)->addDays(30)->toDateString(),
            lines: [
                ['Construction école primaire publique — gros œuvre R+1 (lot 1/2)', 1, 7_800_000],
                ['Études APS / APD et pièces écrites marché', 1, 1_400_000],
            ]
        );

        $this->quote($ucad, 'FYK-DEV-SB0103', ProposalDocumentStatus::Sent, 4_300_000,
            issuedAt: now()->subDays(5)->toDateString(),
            validUntil: now()->subDays(5)->addDays(30)->toDateString(),
            lines: [
                ['Réhabilitation pavillon C — second œuvre complet (réfectoire 320 m²)', 1, 3_500_000],
                ['Mise aux normes électriques et SSI', 1, 800_000],
            ]
        );

        $this->quote($terrouBi, 'FYK-DEV-SB0104', ProposalDocumentStatus::Declined, 6_700_000,
            issuedAt: now()->subDays(28)->toDateString(),
            validUntil: now()->subDays(28)->addDays(30)->toDateString(),
            lines: [
                ['Création espace conférence 200 places — second œuvre + acoustique', 1, 5_400_000],
                ['Aménagement scénique (régie son + éclairage)', 1, 1_300_000],
            ]
        );
    }

    private function seedSowBtpProformas(): void
    {
        $socofim = Client::where('company_id', $this->company->id)->where('name', 'SOCOFIM SA — Promotion Immobilière')->first();
        $poleDiamniadio = Client::where('company_id', $this->company->id)->where('name', 'Pôle Urbain de Diamniadio')->first();
        $ucad = Client::where('company_id', $this->company->id)->where('name', 'Université Cheikh Anta Diop')->first();
        $almadiesPlaza = Client::where('company_id', $this->company->id)->where('name', 'SCI Almadies Plaza')->first();
        $mairieDiamniadio = Client::where('company_id', $this->company->id)->where('name', 'Mairie de Diamniadio')->first();

        // ── Envoyée — gros chantier en attente de PO ───────────────────────
        $this->proforma($socofim, 'FYK-PRO-SB0101', ProposalDocumentStatus::Sent, 18_500_000,
            issuedAt: now()->subDays(10)->toDateString(),
            validUntil: now()->subDays(10)->addDays(60)->toDateString(),
            lines: [
                ['Résidence Les Jardins de Yoff — Tranche 5 (R+5, 36 logements, 1 800 m²)', 1, 15_500_000],
                ['Maîtrise d\'œuvre d\'exécution et coordination SPS', 1, 3_000_000],
            ],
            dossierReference: 'DOSS-2026-SOCOFIM-T5',
            paymentTerms: '20% à la commande, puis situations mensuelles à 30 jours',
            deliveryTerms: 'Démarrage Mai 2026 — livraison T1 2027',
        );

        // ── PO reçu — école primaire publique ──────────────────────────────
        $this->proforma($poleDiamniadio, 'FYK-PRO-SB0102', ProposalDocumentStatus::PoReceived, 14_200_000,
            issuedAt: now()->subDays(25)->toDateString(),
            validUntil: now()->subDays(25)->addDays(60)->toDateString(),
            lines: [
                ['Construction école primaire publique — gros œuvre R+1 (lot 1)', 1, 9_500_000],
                ['Second œuvre + clos couvert (lot 2)', 1, 4_700_000],
            ],
            dossierReference: 'DOSS-2026-PUD-EPP-001',
            paymentTerms: 'Paiement à 60 jours fin de mois sur situations',
            deliveryTerms: '8 mois après ordre de service',
            poReference: 'PUD-MP-2026-0072',
            poReceivedAt: now()->subDays(2)->toDateString(),
            poNotes: 'Marché public 0072 — ordre de service attendu sous 10 jours.',
        );

        // ── Convertie — facture associée créée ─────────────────────────────
        $proformaUcad = $this->proforma($ucad, 'FYK-PRO-SB0103', ProposalDocumentStatus::Converted, 5_400_000,
            issuedAt: now()->subDays(60)->toDateString(),
            validUntil: now()->subDays(60)->addDays(60)->toDateString(),
            lines: [
                ['Réhabilitation pavillon C — Faculté de Médecine (gros œuvre)', 1, 3_800_000],
                ['Mise aux normes électriques et SSI', 1, 1_600_000],
            ],
            dossierReference: 'DOSS-2026-UCAD-PAV-C',
            paymentTerms: '30% à la commande, 40% à mi-parcours, 30% à réception',
            deliveryTerms: '3 mois après ordre de service',
            poReference: 'UCAD-MP-2026-0156',
            poReceivedAt: now()->subDays(45)->toDateString(),
            poNotes: 'Marché interne UCAD — ordre de service signé le 18/03.',
        );
        $this->invoice($ucad, 'FYK-FAC-SB0801', InvoiceStatus::Paid, 5_400_000,
            issuedAt: now()->subDays(40)->toDateString(),
            dueAt: now()->subDays(40)->addDays(30)->toDateString(),
            paidAt: now()->subDays(12)->toDateString(),
            lines: [
                ['Réhabilitation pavillon C — Faculté de Médecine (gros œuvre)', 1, 3_800_000],
                ['Mise aux normes électriques et SSI', 1, 1_600_000],
            ],
            proposalDocumentId: $proformaUcad->id,
        );

        // ── Refusée ────────────────────────────────────────────────────────
        $this->proforma($almadiesPlaza, 'FYK-PRO-SB0104', ProposalDocumentStatus::Declined, 5_200_000,
            issuedAt: now()->subDays(50)->toDateString(),
            validUntil: now()->subDays(50)->addDays(60)->toDateString(),
            lines: [
                ['Réfection complète copropriété — façades + ravalement (Bâtiments A, B, C)', 1, 3_800_000],
                ['Reprise étanchéité toitures-terrasses (3 niveaux)', 1, 1_400_000],
            ],
            dossierReference: 'DOSS-2026-ALMA-RAVAL',
            paymentTerms: '30% à la commande, 70% à la recette finale',
            deliveryTerms: '4 mois',
            notes: 'Devis non retenu — choix d\'un prestataire concurrent par l\'AG des copropriétaires.',
        );

        // ── Brouillon — en cours de chiffrage avec services techniques ─────
        $this->proforma($mairieDiamniadio, 'FYK-PRO-SB0105', ProposalDocumentStatus::Draft, 4_800_000,
            issuedAt: now()->toDateString(),
            validUntil: now()->addDays(60)->toDateString(),
            lines: [
                ['Aménagement places publiques — quartier Cité Mbaye (3 places)', 3, 1_200_000],
                ['Mobilier urbain (bancs, jeux d\'enfants, lampadaires solaires)', 1, 1_200_000],
            ],
            dossierReference: 'DOSS-2026-MAD-PLACES',
            paymentTerms: 'Paiement à 60 jours sur situations',
            deliveryTerms: '5 mois',
            notes: 'En cours de finalisation avec les services techniques municipaux.',
        );
    }

    private function client(string $name, string $phone, string $email, string $address, string $taxId): Client
    {
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
        ?string $sentAt = null,
        ?string $cancelledAt = null,
        ?string $proposalDocumentId = null,
    ): Invoice {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        if ($status === InvoiceStatus::Paid) {
            $amountPaid = $total;
        }

        // Auto-derive sent_at: any status other than Draft means the invoice was sent.
        if ($sentAt === null && $status !== InvoiceStatus::Draft) {
            $sentAt = $issuedAt;
        }

        if ($cancelledAt === null && $status === InvoiceStatus::Cancelled) {
            $cancelledAt = Carbon::parse($issuedAt)->addDays(2)->toDateTimeString();
        }

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'proposal_document_id' => $proposalDocumentId,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'sent_at' => $sentAt,
            'due_at' => $dueAt,
            'paid_at' => $paidAt,
            'cancelled_at' => $cancelledAt,
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
        ProposalDocumentStatus $status,
        int $subtotal,
        string $issuedAt,
        string $validUntil,
        array $lines = [],
        ?string $sentAt = null,
        ?string $acceptedAt = null,
        ?string $declinedAt = null,
    ): ProposalDocument {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        // Auto-derive lifecycle timestamps from the document's issued date so
        // every status carries a coherent activity feed in the demo.
        if ($sentAt === null && $status !== ProposalDocumentStatus::Draft) {
            $sentAt = $issuedAt;
        }
        if ($acceptedAt === null && $status === ProposalDocumentStatus::Accepted) {
            $acceptedAt = Carbon::parse($issuedAt)->addDays(5)->toDateTimeString();
        }
        if ($declinedAt === null && $status === ProposalDocumentStatus::Declined) {
            $declinedAt = Carbon::parse($issuedAt)->addDays(7)->toDateTimeString();
        }

        $quote = ProposalDocument::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => ProposalDocumentType::Quote,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'valid_until' => $validUntil,
            'sent_at' => $sentAt,
            'accepted_at' => $acceptedAt,
            'declined_at' => $declinedAt,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        foreach ($lines as [$description, $quantity, $unitPrice]) {
            ProposalDocumentLine::create([
                'proposal_document_id' => $quote->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $quote;
    }

    /**
     * @param  array<int, array{string, int, int}>  $lines  [description, quantity, unit_price]
     */
    private function proforma(
        Client $client,
        string $reference,
        ProposalDocumentStatus $status,
        int $subtotal,
        string $issuedAt,
        string $validUntil,
        array $lines = [],
        ?string $dossierReference = null,
        ?string $paymentTerms = null,
        ?string $deliveryTerms = null,
        ?string $poReference = null,
        ?string $poReceivedAt = null,
        ?string $poNotes = null,
        ?string $notes = null,
        ?string $sentAt = null,
        ?string $declinedAt = null,
        ?string $convertedAt = null,
    ): ProposalDocument {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;

        // Auto-derive lifecycle timestamps so demo proformas have a coherent
        // activity feed in the UI for every status.
        if ($sentAt === null && $status !== ProposalDocumentStatus::Draft) {
            $sentAt = $issuedAt;
        }
        if ($declinedAt === null && $status === ProposalDocumentStatus::Declined) {
            $declinedAt = Carbon::parse($issuedAt)->addDays(7)->toDateTimeString();
        }
        if ($convertedAt === null && $status === ProposalDocumentStatus::Converted) {
            $convertedAt = $poReceivedAt
                ? Carbon::parse($poReceivedAt)->addDays(3)->toDateTimeString()
                : Carbon::parse($issuedAt)->addDays(10)->toDateTimeString();
        }

        $proforma = ProposalDocument::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => ProposalDocumentType::Proforma,
            'reference' => $reference,
            'status' => $status,
            'issued_at' => $issuedAt,
            'valid_until' => $validUntil,
            'sent_at' => $sentAt,
            'declined_at' => $declinedAt,
            'converted_at' => $convertedAt,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'dossier_reference' => $dossierReference,
            'payment_terms' => $paymentTerms,
            'delivery_terms' => $deliveryTerms,
            'po_reference' => $poReference,
            'po_received_at' => $poReceivedAt,
            'po_notes' => $poNotes,
            'notes' => $notes,
        ]);

        foreach ($lines as [$description, $quantity, $unitPrice]) {
            ProposalDocumentLine::create([
                'proposal_document_id' => $proforma->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 18,
                'total' => $quantity * $unitPrice,
            ]);
        }

        return $proforma;
    }
}
