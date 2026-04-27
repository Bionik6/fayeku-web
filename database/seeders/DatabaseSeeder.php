<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Identifiants de démo (mot de passe : `password` partout).
 *
 * ┌── Cabinet : Cabinet Ndiaye Conseil ─────────────────────────────────────┐
 * │ Owner   Aminata Ndiaye  email aminata@cabinet-ndiaye.test  /accountant/login │
 * │ Admin   Fatou Sarr      email fatou@cabinet-ndiaye.test    /accountant/login │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Diop Services SARL (workspace riche : factures, devis, relances) ┐
 * │ Owner   Moussa Diop     phone 77 445 76 33  /sme/login                  │
 * │ Member  Awa Ba          phone 77 445 76 35  /sme/login                  │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Sow BTP SARL (PME secondaire, données minimales) ────────────────┐
 * │ Owner   Ibrahima Sow    phone 77 445 76 36  /sme/login                  │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * Le portefeuille du cabinet inclut Diop Services, Sow BTP, et 23 PME
 * anonymes générées par DemoComptablePortfolioSeeder (commissions,
 * invitations, factures variées).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DunningTemplateSeeder::class,
            DemoAccountsSeeder::class,
            DemoPmeWorkspaceSeeder::class,
            DemoComptablePortfolioSeeder::class,
        ]);
    }
}
