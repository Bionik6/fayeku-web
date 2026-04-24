<?php

/*
|--------------------------------------------------------------------------
| Fayeku — Catalogue des templates WhatsApp
|--------------------------------------------------------------------------
|
| Ce fichier expose :
|   - `name`    → nom Meta du template (source de vérité en prod).
|   - `subject` → sujet de l'email fallback Resend (invention locale).
|   - `body`    → FALLBACK local uniquement. En prod, les bodies sont récupérés
|                 à chaud depuis Meta Graph API par MetaTemplateFetcher, mis
|                 en cache 24 h et l'aperçu / l'email utilisent la version
|                 fraîche de Meta. Le body ci-dessous sert de filet de sécurité
|                 (tests, dev sans credentials, API Meta injoignable).
|
| Bodies locaux = format `{variable}` (brace simple) → substitution strtr().
| Bodies Meta   = format `{{variable}}` (brace double) → substitution strtr().
| Les deux paths sont gérés par WhatsAppTemplateCatalog::substitute().
|
| Pour forcer un refresh du cache : `php artisan whatsapp:templates:sync`.
|
*/

return [

    // ─── Relances manuelles (déclenchées par clic utilisateur) ──────────────

    'reminder_manual_cordial' => [
        'name' => 'fayeku_reminder_invoice_due_manual_cordial',
        'subject' => 'Rappel — facture {invoice_number}',
        'body' => <<<'TEXT'
*Rappel — facture en attente*

Bonjour {client_name},

*{company_name}* souhaite vous rappeler que la facture *{invoice_number}* d'un montant de *{invoice_amount}*, échue le *{due_date}*, reste en attente de règlement.

Nous vous serions reconnaissants de bien vouloir procéder au paiement dans les meilleurs délais.

_Si le paiement a déjà été effectué, merci de ne pas tenir compte de ce message._

Cordialement,

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_manual_firm' => [
        'name' => 'fayeku_reminder_invoice_due_manual_firm',
        'subject' => 'Facture {invoice_number} en retard de paiement',
        'body' => <<<'TEXT'
*Facture en retard de paiement*

Bonjour {client_name},

La facture *{invoice_number}* d'un montant de *{invoice_amount}*, émise par *{company_name}*, est en retard de paiement depuis le *{due_date}*.

Nous vous demandons de procéder au règlement dans les plus brefs délais.

Dans l'attente de votre règlement,

_Si le paiement a déjà été effectué, merci de ne pas tenir compte de ce message._

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_manual_urgent' => [
        'name' => 'fayeku_reminder_invoice_due_manual_urgent',
        'subject' => 'Action requise — facture {invoice_number} impayée',
        'body' => <<<'TEXT'
*Action requise — paiement en retard*

URGENT : {client_name}, la facture *{invoice_number}* d'un montant de *{invoice_amount}*, émise par *{company_name}*, est impayée depuis le *{due_date}*. Malgré nos précédentes relances, aucun règlement n'a été effectué.

Nous vous prions de régulariser cette situation immédiatement.

_Si le paiement vient d'être effectué, merci de ne pas tenir compte de ce message._

En espérant une action immédiate de votre part,

{sender_signature} via Fayeku
TEXT,
    ],

    // ─── Relances automatiques (déclenchées par le cron selon dunning_strategy) ──

    'reminder_auto_m3' => [
        'name' => 'fayeku_reminder_invoice_due_auto_m3',
        'subject' => 'Échéance dans 3 jours — facture {invoice_number}',
        'body' => <<<'TEXT'
Bonjour {client_name},

Nous vous informons que la facture *{invoice_number}* d'un montant de *{invoice_amount}* arrive à échéance le *{due_date}* (dans 3 jours).

Merci d'anticiper votre règlement.

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_auto_0' => [
        'name' => 'fayeku_reminder_invoice_due_auto_0',
        'subject' => 'Échéance aujourd\'hui — facture {invoice_number}',
        'body' => <<<'TEXT'
Bonjour {client_name},

*{company_name}* vous informe que la facture *{invoice_number}* d'un montant de *{invoice_amount}* arrive à échéance aujourd'hui *{due_date}*.

Merci de procéder au règlement.

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_auto_p3' => [
        'name' => 'fayeku_reminder_invoice_due_auto_p3',
        'subject' => 'Facture {invoice_number} — 3 jours de retard',
        'body' => <<<'TEXT'
Bonjour {client_name},

*{company_name}* constate que la facture *{invoice_number}* d'un montant de *{invoice_amount}*, échue le *{due_date}*, reste impayée depuis 3 jours.

Merci de régulariser votre situation.

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_auto_p7' => [
        'name' => 'fayeku_reminder_invoice_due_auto_p7',
        'subject' => 'Facture {invoice_number} — 7 jours de retard',
        'body' => <<<'TEXT'
Bonjour {client_name},

*{company_name}* vous relance : la facture *{invoice_number}* d'un montant de *{invoice_amount}*, échue le *{due_date}*, reste impayée depuis 7 jours.

Merci de régler dans les plus brefs délais.

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_auto_p15' => [
        'name' => 'fayeku_reminder_invoice_due_auto_p15',
        'subject' => 'Facture {invoice_number} — 15 jours de retard',
        'body' => <<<'TEXT'
Bonjour {client_name},

*{company_name}* revient vers vous : la facture *{invoice_number}* d'un montant de *{invoice_amount}*, échue le *{due_date}*, est en retard de 15 jours.

Nous vous invitons à procéder au règlement sans délai.

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_auto_p30' => [
        'name' => 'fayeku_reminder_invoice_due_auto_p30',
        'subject' => 'Facture {invoice_number} — 30 jours de retard',
        'body' => <<<'TEXT'
Bonjour {client_name},

Malgré nos précédents rappels, la facture *{invoice_number}* d'un montant de *{invoice_amount}*, échue le *{due_date}*, reste impayée depuis 30 jours.

Merci de nous contacter pour régulariser votre situation.

{sender_signature} via Fayeku
TEXT,
    ],

    'reminder_auto_p60' => [
        'name' => 'fayeku_reminder_invoice_due_auto_p60',
        'subject' => 'Facture {invoice_number} — 60 jours de retard',
        'body' => <<<'TEXT'
Bonjour {client_name},

Malgré nos précédents rappels, la facture *{invoice_number}* d'un montant de *{invoice_amount}*, échue le *{due_date}*, reste impayée depuis 60 jours.

Nous vous demandons de prendre contact avec *{company_name}* dans les plus brefs délais.

{sender_signature} via Fayeku
TEXT,
    ],

    // ─── Notifications de cycle de vie ──────────────────────────────────────

    'notification_invoice_sent' => [
        'name' => 'fayeku_notification_invoice_sent',
        'subject' => 'Nouvelle facture {invoice_number}',
        'body' => <<<'TEXT'
*Nouvelle facture*

Bonjour {client_name},

*{company_name}* vous informe que la facture *{invoice_number}* d'un montant de *{invoice_amount}* est désormais disponible.

Nous vous invitons à la consulter dès que possible.

_Si vous avez déjà pris connaissance de cette facture, merci de ne pas tenir compte de ce message._

Cordialement,

{sender_signature} via Fayeku
TEXT,
    ],

    'notification_invoice_sent_with_due_date' => [
        'name' => 'fayeku_notification_invoice_sent_with_due_date',
        'subject' => 'Facture {invoice_number} disponible — échéance {invoice_due_date}',
        'body' => <<<'TEXT'
*Facture disponible*

Bonjour {client_name},

*{company_name}* vous informe que la facture *{invoice_number}* d'un montant de *{invoice_amount}* a été émise, avec une échéance fixée au *{invoice_due_date}*.

Nous vous invitons à la consulter dès que possible.

Cordialement,

{sender_signature} via Fayeku
TEXT,
    ],

    'notification_invoice_partially_paid' => [
        'name' => 'fayeku_notification_invoice_partially_paid',
        'subject' => 'Paiement partiel reçu — facture {invoice_number}',
        'body' => <<<'TEXT'
*Paiement partiel reçu*

Bonjour {client_name},

*{company_name}* confirme la réception de votre paiement de *{amount_paid}* le *{payment_date}* pour la facture *{invoice_number}*.

Solde restant dû : *{amount_remaining}*.

Merci de finaliser votre règlement dès que possible.

Cordialement,

{sender_signature} via Fayeku
TEXT,
    ],

    'notification_invoice_paid_full' => [
        'name' => 'fayeku_notification_invoice_paid_full',
        'subject' => 'Paiement reçu — facture {invoice_number} soldée',
        'body' => <<<'TEXT'
*Paiement reçu — merci*

Bonjour {client_name},

*{company_name}* confirme la réception de votre paiement de *{amount_paid}* pour la facture *{invoice_number}*, reçu le *{payment_date}*.

La facture est désormais considérée comme soldée. Nous vous remercions pour votre règlement.

Cordialement,

{sender_signature} via Fayeku
TEXT,
    ],

    'notification_quote_sent' => [
        'name' => 'fayeku_notification_quote_sent',
        'subject' => 'Nouveau devis {quote_number}',
        'body' => <<<'TEXT'
*Devis disponible*

Bonjour {client_name},

*{company_name}* vous a envoyé le devis *{quote_number}* d'un montant de *{quote_amount}*, valable jusqu'au *{expiry_date}*.

Consultez le détail et validez le devis en cliquant sur le bouton ci-dessous.

Cordialement,

{sender_signature} via Fayeku
TEXT,
    ],

    // ─── Authentification (OTP) ─────────────────────────────────────────────
    //
    // Template catégorie AUTHENTICATION côté Meta. Utilisé tant qu'Orange IAM
    // n'a pas validé le compte SMS ; à terme, bascule vers SmsOtpChannel via
    // OTP_CHANNEL=sms sans toucher au code.

    'otp_code' => [
        'name' => 'fayeku_otp_verification',
        'subject' => 'Votre code Fayeku',
        // Fallback local reproduisant la structure des templates Meta
        // AUTHENTICATION (1 variable positionnelle + footers sécurité/expiration).
        // Meta rend la vraie copie, celle-ci sert uniquement à l'aperçu local,
        // au mail Resend et aux tests.
        'body' => <<<'TEXT'
*{{1}}* est votre code de vérification.

Pour votre sécurité, ne partagez pas ce code.
TEXT,
    ],

];
