<?php

namespace Database\Seeders;

use App\Models\PME\DunningTemplate;
use Illuminate\Database\Seeder;

class DunningTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            0 => <<<'MSG'
                Bonjour {contact_name},

                Petit rappel : la facture {invoice_reference} d'un montant de {amount} {currency} arrive à échéance aujourd'hui, {due_date}.

                La facture est jointe pour référence. Moyens de paiement : Wave, Orange Money, virement bancaire.

                Merci,
                {signature}
                MSG,

            3 => <<<'MSG'
                Bonjour {contact_name},

                La facture {invoice_reference} d'un montant de {amount} {currency} est arrivée à échéance le {due_date}.

                Pourriez-vous confirmer le règlement ou m'indiquer la date prévue ?

                Je joins la facture pour rappel. Moyens de paiement : Wave, Orange Money, virement bancaire.

                Merci,
                {signature}
                MSG,

            7 => <<<'MSG'
                Bonjour {contact_name},

                Je reviens vers vous concernant la facture {invoice_reference} ({amount} {currency}), toujours en attente depuis le {due_date}.

                Pourriez-vous me faire un retour sur son traitement ? Si un point bloque, n'hésitez pas à me le signaler.

                Cordialement,
                {signature}
                MSG,

            15 => <<<'MSG'
                Bonjour {contact_name},

                La facture {invoice_reference} d'un montant de {amount} {currency} reste impayée depuis 15 jours (échéance du {due_date}).

                Merci de régulariser cette situation dans les meilleurs délais, ou de me contacter pour convenir d'un échéancier si nécessaire.

                Cordialement,
                {signature}
                MSG,

            30 => <<<'MSG'
                Bonjour {contact_name},

                La facture {invoice_reference} ({amount} {currency}) accuse désormais 30 jours de retard depuis son échéance du {due_date}.

                Merci de procéder au règlement sous 7 jours ouvrés, ou de me contacter pour discuter de la situation.

                Cordialement,
                {signature}
                MSG,
        ];

        foreach ($templates as $offset => $body) {
            DunningTemplate::updateOrCreate(
                ['day_offset' => $offset],
                ['body' => trim(preg_replace('/^ {16}/m', '', $body)), 'active' => true]
            );
        }
    }
}
