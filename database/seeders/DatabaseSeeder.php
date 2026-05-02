<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Identifiants de démo (mot de passe : `password` partout). Tous les
 * comptes se connectent par email sur /login.
 *
 * Chaque entité est dotée de 2 hommes + 1 femme pour couvrir tous les
 * rôles (Owner, Admin, Member).
 *
 * ┌── Cabinet : Cabinet Ndiaye Conseil ──────────────────────────────────────┐
 * │ Owner   Ousmane Ndiaye   ousmane@cabinet-ndiaye.test           (H)      │
 * │ Admin   Mamadou Sarr     mamadou@cabinet-ndiaye.test           (H)      │
 * │ Admin   Aminata Ndiaye   aminata@cabinet-ndiaye.test           (F)      │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Diop Services SARL (services numériques — workspace riche) ──────┐
 * │ Owner   Moussa Diop      moussa@diop-services.test             (H)      │
 * │ Admin   Cheikh Diop      cheikh@diop-services.test             (H)      │
 * │ Member  Awa Ba           awa@diop-services.test                (F)      │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * ┌── PME : Sow BTP SARL (BTP / promotion immobilière — workspace riche) ────┐
 * │ Owner   Ibrahima Sow     ibrahima@sow-btp.test                 (H)      │
 * │ Admin   Modou Fall       modou@sow-btp.test                    (H)      │
 * │ Member  Khady Diallo     khady@sow-btp.test                    (F)      │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * Les deux PME ont chacune un historique complet (factures payées sur
 * plusieurs mois, retards avec relances, brouillons, devis variés) pour
 * exercer tous les écrans PME. Le portefeuille du cabinet inclut Diop
 * Services, Sow BTP, et 23 PME anonymes générées par
 * DemoComptablePortfolioSeeder (commissions, invitations, factures variées).
 *
 * RealAccountsSeeder ajoute 4 comptes sur de vraies adresses email
 * (bionik6, iamibrahimaciss, icissdev, callmeibou) pour tester en bout-en-bout
 * les flows magic link, reset password et login. Voir le docblock du seeder
 * pour la répartition cabinet/PME.
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
            RealAccountsSeeder::class,
        ]);
    }
}
