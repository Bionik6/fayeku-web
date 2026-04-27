<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Identifiants de démo (mot de passe : `password` partout).
 *
 * Chaque entité est dotée de 2 hommes + 1 femme pour couvrir tous les
 * rôles (Owner, Admin, Member). Les comptables se connectent par email
 * sur /accountant/login, les PME par téléphone sur /sme/login.
 *
 * ┌── Cabinet : Cabinet Ndiaye Conseil ──────────────────────────────────────┐
 * │ Owner   Ousmane Ndiaye   email ousmane@cabinet-ndiaye.test   (H)        │
 * │ Admin   Mamadou Sarr     email mamadou@cabinet-ndiaye.test   (H)        │
 * │ Admin   Aminata Ndiaye   email aminata@cabinet-ndiaye.test   (F)        │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Diop Services SARL (services numériques — workspace riche) ──────┐
 * │ Owner   Moussa Diop      phone +221 77 445 76 33   (H)                  │
 * │ Admin   Cheikh Diop      phone +221 77 445 76 37   (H)                  │
 * │ Member  Awa Ba           phone +221 77 445 76 35   (F)                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Sow BTP SARL (BTP / promotion immobilière — workspace riche) ────┐
 * │ Owner   Ibrahima Sow     phone +221 77 445 76 36   (H)                  │
 * │ Admin   Modou Fall       phone +221 77 445 76 38   (H)                  │
 * │ Member  Khady Diallo     phone +221 77 445 76 39   (F)                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * Les deux PME ont chacune un historique complet (factures payées sur
 * plusieurs mois, retards avec relances, brouillons, devis variés) pour
 * exercer tous les écrans PME. Le portefeuille du cabinet inclut Diop
 * Services, Sow BTP, et 23 PME anonymes générées par
 * DemoComptablePortfolioSeeder (commissions, invitations, factures variées).
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
