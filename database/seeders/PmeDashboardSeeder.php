<?php

namespace Database\Seeders;

use Database\Factories\Support\SenegalFaker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Enums\ReminderStatus;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Enums\QuoteStatus;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\PME\Invoicing\Models\InvoiceLine;
use Modules\PME\Invoicing\Models\Quote;
use Modules\PME\Invoicing\Models\QuoteLine;
use Modules\Shared\Models\User;

class PmeDashboardSeeder extends Seeder
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
        $user = User::query()->create([
            'first_name' => 'Oumar',
            'last_name' => 'Faye',
            'phone' => '+221770000001',
            'password' => 'passer1234',
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $this->company = Company::query()->create([
            'name' => 'Faye & Associés SARL',
            'type' => 'sme',
            'plan' => 'essentiel',
            'country_code' => 'SN',
            'phone' => '+221338200001',
            'email' => 'contact@faye-associes.sn',
            'address' => '15 Avenue Bourguiba',
            'city' => 'Dakar',
            'ninea' => 'SN20240001',
            'rccm' => 'SN-DKR-2024-B-00001',
        ]);

        $this->company->users()->attach($user->id, ['role' => 'owner']);

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
        // ── Clients ──────────────────────────────────────────────────────────
        $sonatel = $this->client('Sonatel SA', '+221338200010', 'facturation@sonatel.sn');
        $dakarPharma = $this->client('Dakar Pharma', '+221338200011', 'admin@dakar-pharma.sn');
        $atlan = $this->client('Immeuble ATLAN SARL', '+221338200012', 'direction@atlan.sn');
        $transco = $this->client('Transco SARL', '+221338200013', 'compta@transco.sn');
        $senwater = $this->client('Sénégal Water Services', '+221338200014', 'contact@senwater.sn');
        $portDakar = $this->client('Port de Dakar SA', '+221338200015', 'finance@portdakar.sn');
        $africomm = $this->client('Africomm CI SARL', '+221338200016', 'admin@africomm.sn');
        $btpHorizon = $this->client('BTP Horizon SARL', '+221338200017', 'direction@btphorizon.sn');

        // ── Décembre 2025 — 4 factures payées ────────────────────────────────
        $this->invoice($sonatel, 'FYK-FAC-A7B3K2', InvoiceStatus::Paid, 650_000,
            issuedAt: '2025-12-05', dueAt: '2026-01-04', paidAt: '2025-12-28',
            lines: [
                ['Prestation de conseil en stratégie commerciale', 2, 250_000],
                ['Rapport mensuel de suivi', 1, 150_000],
            ]
        );

        $this->invoice($portDakar, 'FYK-FAC-C9D4M5', InvoiceStatus::Paid, 800_000,
            issuedAt: '2025-12-10', dueAt: '2026-01-09', paidAt: '2026-01-05',
            lines: [
                ['Audit logistique portuaire', 1, 600_000],
                ['Frais de déplacement', 1, 200_000],
            ]
        );

        $this->invoice($btpHorizon, 'FYK-FAC-E2F8N1', InvoiceStatus::Paid, 450_000,
            issuedAt: '2025-12-15', dueAt: '2026-01-14', paidAt: '2025-12-30',
            lines: [
                ['Étude technique de faisabilité', 1, 350_000],
                ['Documentation technique', 1, 100_000],
            ]
        );

        $this->invoice($africomm, 'FYK-FAC-G5H7P4', InvoiceStatus::Paid, 550_000,
            issuedAt: '2025-12-20', dueAt: '2026-01-19', paidAt: '2026-01-10',
            lines: [
                ['Intégration système de communication', 1, 400_000],
                ['Formation utilisateurs', 2, 75_000],
            ]
        );

        // ── Janvier 2026 — 5 factures payées ─────────────────────────────────
        $this->invoice($dakarPharma, 'FYK-FAC-J1K6Q9', InvoiceStatus::Paid, 720_000,
            issuedAt: '2026-01-08', dueAt: '2026-02-07', paidAt: '2026-02-03',
            lines: [
                ['Assistance réglementaire pharmaceutique', 3, 200_000],
                ['Rédaction dossier AMM', 1, 120_000],
            ]
        );

        $this->invoice($transco, 'FYK-FAC-L3M0R7', InvoiceStatus::Paid, 380_000,
            issuedAt: '2026-01-12', dueAt: '2026-02-11', paidAt: '2026-02-08',
            lines: [
                ['Optimisation des flux logistiques', 1, 280_000],
                ['Rapport d\'analyse', 1, 100_000],
            ]
        );

        $this->invoice($sonatel, 'FYK-FAC-N8P2S5', InvoiceStatus::Paid, 920_000,
            issuedAt: '2026-01-18', dueAt: '2026-02-17', paidAt: '2026-02-12',
            lines: [
                ['Déploiement infrastructure réseau (phase 1)', 1, 700_000],
                ['Tests et validation', 1, 220_000],
            ]
        );

        $this->invoice($senwater, 'FYK-FAC-Q4R9T3', InvoiceStatus::Paid, 480_000,
            issuedAt: '2026-01-25', dueAt: '2026-02-24', paidAt: '2026-02-20',
            lines: [
                ['Diagnostic réseau de distribution', 1, 380_000],
                ['Plan d\'amélioration', 1, 100_000],
            ]
        );

        $this->invoice($portDakar, 'FYK-FAC-S6U1V8', InvoiceStatus::Paid, 680_000,
            issuedAt: '2026-01-28', dueAt: '2026-02-27', paidAt: '2026-02-25',
            lines: [
                ['Optimisation gestion des conteneurs', 1, 550_000],
                ['Formation équipe (3 jours)', 3, 43_333],
            ]
        );

        // ── Février 2026 — 4 factures payées ─────────────────────────────────
        $this->invoice($btpHorizon, 'FYK-FAC-T2W5X0', InvoiceStatus::Paid, 850_000,
            issuedAt: '2026-02-05', dueAt: '2026-03-07', paidAt: '2026-03-04',
            lines: [
                ['Coordination travaux phase 2', 1, 650_000],
                ['Suivi chantier hebdomadaire (4 semaines)', 4, 50_000],
            ]
        );

        $this->invoice($africomm, 'FYK-FAC-V7Y3Z1', InvoiceStatus::Paid, 560_000,
            issuedAt: '2026-02-10', dueAt: '2026-03-12', paidAt: '2026-03-08',
            lines: [
                ['Déploiement solution VoIP', 1, 420_000],
                ['Maintenance préventive', 2, 70_000],
            ]
        );

        $this->invoice($senwater, 'FYK-FAC-W4A8B6', InvoiceStatus::Paid, 440_000,
            issuedAt: '2026-02-14', dueAt: '2026-03-16', paidAt: '2026-03-12',
            lines: [
                ['Audit qualité eau potable', 1, 350_000],
                ['Rapport de conformité', 1, 90_000],
            ]
        );

        $this->invoice($transco, 'FYK-FAC-X9C2D4', InvoiceStatus::Paid, 620_000,
            issuedAt: '2026-02-20', dueAt: '2026-03-22', paidAt: '2026-03-18',
            lines: [
                ['Refonte du système de tracking', 1, 500_000],
                ['Tests et mise en production', 1, 120_000],
            ]
        );

        // ── Impayés en retard ─────────────────────────────────────────────────

        // Critique (J+64) : émise le 20 déc → échéance 19 jan → 64 jours de retard
        $facAtlan = $this->invoice($atlan, 'FYK-FAC-Y5E7F3', InvoiceStatus::Overdue, 720_000,
            issuedAt: '2025-12-20', dueAt: '2026-01-19', paidAt: null,
            lines: [
                ['Gestion administrative immeuble — janv.', 1, 500_000],
                ['Frais de coordination travaux', 1, 220_000],
            ]
        );

        // 2 relances envoyées pour FYK-FAC-Y5E7F3
        Reminder::create([
            'invoice_id' => $facAtlan->id,
            'channel' => ReminderChannel::Email,
            'status' => ReminderStatus::Sent,
            'sent_at' => now()->subDays(45),
            'message_body' => 'Rappel de paiement : facture FYK-FAC-Y5E7F3 échue depuis plus de 30 jours.',
            'recipient_email' => 'direction@atlan.sn',
        ]);

        Reminder::create([
            'invoice_id' => $facAtlan->id,
            'channel' => ReminderChannel::WhatsApp,
            'status' => ReminderStatus::Sent,
            'sent_at' => now()->subDays(15),
            'message_body' => 'Bonjour, nous revenons vers vous concernant la facture FYK-FAC-Y5E7F3 de 849 600 F toujours impayée. Merci de régulariser.',
            'recipient_phone' => '+221338200012',
        ]);

        // Attention (J+31) : émise le 22 jan → échéance 21 fév → 31 jours de retard
        $this->invoice($dakarPharma, 'FYK-FAC-Z1G4H8', InvoiceStatus::Overdue, 450_000,
            issuedAt: '2026-01-22', dueAt: '2026-02-21', paidAt: null,
            lines: [
                ['Conseil en stratégie pharmaceutique', 2, 180_000],
                ['Veille réglementaire mensuelle', 1, 90_000],
            ]
        );

        // ── Mars 2026 — 6 factures payées ────────────────────────────────────
        $this->invoice($btpHorizon, 'FYK-FAC-B3J6K0', InvoiceStatus::Paid, 900_000,
            issuedAt: '2026-03-03', dueAt: '2026-04-02', paidAt: '2026-03-10',
            lines: [
                ['Coordination chantier résidence Les Almadies', 1, 700_000],
                ['Rapport de suivi hebdomadaire (3 semaines)', 3, 66_667],
            ]
        );

        $this->invoice($sonatel, 'FYK-FAC-D8L1M5', InvoiceStatus::Paid, 1_000_000,
            issuedAt: '2026-03-05', dueAt: '2026-04-04', paidAt: '2026-03-15',
            lines: [
                ['Déploiement infrastructure réseau (phase 2)', 1, 800_000],
                ['Tests et recettage', 1, 200_000],
            ]
        );

        $this->invoice($portDakar, 'FYK-FAC-F2N9P7', InvoiceStatus::Paid, 700_000,
            issuedAt: '2026-03-10', dueAt: '2026-04-09', paidAt: '2026-03-18',
            lines: [
                ['Conseil en gestion portuaire', 1, 550_000],
                ['Formation managers (2 jours)', 2, 75_000],
            ]
        );

        $this->invoice($btpHorizon, 'FYK-FAC-H4Q3R2', InvoiceStatus::Paid, 550_000,
            issuedAt: '2026-03-12', dueAt: '2026-04-11', paidAt: '2026-03-20',
            lines: [
                ['Suivi chantier résidence (mars)', 1, 420_000],
                ['Rapport de suivi hebdomadaire', 4, 32_500],
            ]
        );

        $this->invoice($senwater, 'FYK-FAC-K7S8T6', InvoiceStatus::Paid, 750_000,
            issuedAt: '2026-03-14', dueAt: '2026-04-13', paidAt: '2026-03-21',
            lines: [
                ['Audit complet réseau de distribution Q1', 1, 600_000],
                ['Plan d\'action et recommandations', 1, 150_000],
            ]
        );

        $this->invoice($sonatel, 'FYK-FAC-M1U4V9', InvoiceStatus::Paid, 400_000,
            issuedAt: '2026-03-22', dueAt: '2026-04-21', paidAt: '2026-03-23',
            lines: [
                ['Maintenance corrective système', 1, 300_000],
                ['Support technique (8h)', 8, 12_500],
            ]
        );

        // ── Mars 2026 — 5 factures envoyées (à encaisser) ────────────────────
        $this->invoice($dakarPharma, 'FYK-FAC-P5W2X1', InvoiceStatus::Sent, 1_200_000,
            issuedAt: '2026-03-23', dueAt: '2026-04-22', paidAt: null,
            lines: [
                ['Audit qualité laboratoire pharmaceutique', 1, 900_000],
                ['Plan d\'action qualité et conformité', 1, 300_000],
            ]
        );

        $this->invoice($transco, 'FYK-FAC-R9Y6Z4', InvoiceStatus::Sent, 680_000,
            issuedAt: '2026-03-22', dueAt: '2026-04-21', paidAt: null,
            lines: [
                ['Système de tracking temps réel', 1, 550_000],
                ['Documentation technique et formation', 1, 130_000],
            ]
        );

        $this->invoice($africomm, 'FYK-FAC-T3A7B8', InvoiceStatus::Sent, 500_000,
            issuedAt: '2026-03-21', dueAt: '2026-04-20', paidAt: null,
            lines: [
                ['Intégration API partenaires', 1, 380_000],
                ['Tests d\'intégration et recettage', 1, 120_000],
            ]
        );

        $this->invoice($senwater, 'FYK-FAC-V6C1D5', InvoiceStatus::Sent, 360_000,
            issuedAt: '2026-03-18', dueAt: '2026-04-17', paidAt: null,
            lines: [
                ['Analyse qualité réseau Q1 2026', 1, 280_000],
                ['Recommandations techniques', 1, 80_000],
            ]
        );

        $this->invoice($portDakar, 'FYK-FAC-X0E4F2', InvoiceStatus::Sent, 420_000,
            issuedAt: '2026-03-16', dueAt: '2026-04-15', paidAt: null,
            lines: [
                ['Optimisation flux conteneurs Q1', 1, 350_000],
                ['Tableau de bord KPI', 1, 70_000],
            ]
        );
    }

    private function client(string $name, string $phone, string $email): Client
    {
        return Client::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => SenegalFaker::address(),
            'tax_id' => 'SN'.strtoupper(fake()->numerify('##########')),
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
    ): Invoice {
        $taxAmount = (int) round($subtotal * 0.18);
        $total = $subtotal + $taxAmount;
        $amountPaid = $status === InvoiceStatus::Paid ? $total : 0;

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

    private function seedQuotes(): void
    {
        // Re-fetch clients (already created in seedInvoices)
        $sonatel = Client::where('company_id', $this->company->id)->where('name', 'Sonatel SA')->first();
        $btpHorizon = Client::where('company_id', $this->company->id)->where('name', 'BTP Horizon SARL')->first();
        $transco = Client::where('company_id', $this->company->id)->where('name', 'Transco SARL')->first();
        $africomm = Client::where('company_id', $this->company->id)->where('name', 'Africomm CI SARL')->first();

        // FYK-DEV-FKM90W · Sonatel SA · 850 000 F · Accepté il y a 3 semaines
        $this->quote($sonatel, 'FYK-DEV-FKM90W', QuoteStatus::Accepted, 850_000,
            issuedAt: now()->subDays(21)->toDateString(),
            validUntil: now()->subDays(21)->addDays(30)->toDateString(),
            lines: [
                ['Déploiement solution de supervision réseau', 1, 700_000],
                ['Documentation et transfert de compétences', 1, 150_000],
            ]
        );

        // FYK-DEV-ALR4HD · BTP Horizon · 1 200 000 F · Envoyé il y a 5 jours
        $this->quote($btpHorizon, 'FYK-DEV-ALR4HD', QuoteStatus::Sent, 1_200_000,
            issuedAt: now()->subDays(5)->toDateString(),
            validUntil: now()->subDays(5)->addDays(30)->toDateString(),
            lines: [
                ['Coordination chantier complexe résidentiel Almadies (phase 3)', 1, 950_000],
                ['Études et plans d\'exécution', 1, 250_000],
            ]
        );

        // FYK-DEV-B7N2P5 · Transco SARL · 680 000 F · Envoyé il y a 8 jours
        $this->quote($transco, 'FYK-DEV-B7N2P5', QuoteStatus::Sent, 680_000,
            issuedAt: now()->subDays(8)->toDateString(),
            validUntil: now()->subDays(8)->addDays(30)->toDateString(),
            lines: [
                ['Refonte du système de gestion de flotte', 1, 550_000],
                ['Formation équipe chauffeurs et dispatchers', 2, 65_000],
            ]
        );

        // FYK-DEV-C4Q8R1 · Africomm CI · 450 000 F · Refusé il y a 12 jours
        $this->quote($africomm, 'FYK-DEV-C4Q8R1', QuoteStatus::Declined, 450_000,
            issuedAt: now()->subDays(12)->toDateString(),
            validUntil: now()->subDays(12)->addDays(30)->toDateString(),
            lines: [
                ['Migration infrastructure vers le cloud', 1, 380_000],
                ['Audit sécurité et conformité', 1, 70_000],
            ]
        );
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
