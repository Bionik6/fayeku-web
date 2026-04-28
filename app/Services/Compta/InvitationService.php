<?php

namespace App\Services\Compta;

use App\Models\Auth\Company;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;

class InvitationService
{
    private const PLAN_LABELS = [
        'basique' => 'Basique',
        'essentiel' => 'Essentiel',
    ];

    private const PLAN_PRICES = [
        'basique' => '10 000',
        'essentiel' => '20 000',
    ];

    /**
     * Compose the WhatsApp message body for the given context.
     */
    public function composeWhatsAppMessage(PartnerInvitation $invitation, string $context = 'invite'): string
    {
        [$contactFirstName, $firmName, $userFirstName, $userLastName, $planLabel, $planPrice, $link, $signatureName]
            = $this->resolveTemplateValues($invitation);

        $greeting = $contactFirstName !== '' ? "Bonjour {$contactFirstName}," : 'Bonjour,';

        if ($context === 'reminder') {
            return <<<TXT
{$greeting}

Petit rappel de la part de {$firmName} : votre invitation à rejoindre *Fayeku* est toujours active.

Pour rappel, vous bénéficiez de *2 mois offerts* sur le plan {$planLabel} ({$planPrice} FCFA/mois ensuite).

Activez votre compte ici 👉 {$link}

Le lien est valable 30 jours. Je reste disponible si vous avez la moindre question.

{$signatureName}
{$firmName}
TXT;
        }

        return <<<TXT
{$greeting}

{$firmName} vous invite à rejoindre *Fayeku*, la plateforme qui simplifie votre facturation et vos relances clients.

En passant par mon lien partenaire, vous bénéficiez de *2 mois offerts* sur le plan {$planLabel} ({$planPrice} FCFA/mois ensuite).

Ce que Fayeku vous apporte concrètement :
✓ Factures pro en quelques clics
✓ Relances Email/WhatsApp automatiques sur vos impayés
✓ Vision claire de votre trésorerie à 30 et 90 jours
✓ Collaboration directe avec notre cabinet

Activez votre compte ici 👉 {$link}

Le lien est valable 30 jours. Je reste disponible si vous avez la moindre question.

{$signatureName}
{$firmName}
TXT;
    }

    /**
     * Compose the email subject and body for the given context.
     *
     * @return array{subject: string, body: string}
     */
    public function composeEmail(PartnerInvitation $invitation, string $context = 'invite'): array
    {
        [$contactFirstName, $firmName, $userFirstName, $userLastName, $planLabel, $planPrice, $link, $signatureName]
            = $this->resolveTemplateValues($invitation);

        $greeting = $contactFirstName !== '' ? "Bonjour {$contactFirstName}," : 'Bonjour,';
        $signatureFull = trim($userFirstName.' '.$userLastName);
        $signatureFull = $signatureFull !== '' ? $signatureFull : $signatureName;

        if ($context === 'reminder') {
            $subject = "[Rappel] {$firmName} vous invite sur Fayeku";

            $body = <<<TXT
{$greeting}

Petit rappel : votre invitation à rejoindre Fayeku, transmise par {$firmName}, est toujours active.

Vous bénéficiez toujours de 2 mois offerts sur le plan {$planLabel} ({$planPrice} FCFA/mois ensuite, sans engagement).

Activer mon compte Fayeku :
{$link}

Le lien est valable 30 jours. N'hésitez pas à me contacter directement si vous souhaitez en discuter ou si vous avez besoin d'aide pour la mise en route.

Bien cordialement,

{$signatureFull}
{$firmName}
TXT;

            return ['subject' => $subject, 'body' => $body];
        }

        $subject = "{$firmName} vous invite sur Fayeku";

        $body = <<<TXT
{$greeting}

Notre cabinet utilise désormais Fayeku pour structurer le suivi de la facturation et des impayés de nos clients PME, et je souhaitais vous y inviter.

Fayeku vous permet concrètement de :
  • Émettre vos factures clients en quelques clics, avec un rendu professionnel
  • Automatiser vos relances Email/WhatsApp sur les factures impayées
  • Suivre votre trésorerie prévisionnelle à 30 et 90 jours
  • Collaborer plus simplement avec notre cabinet (transmission de pièces, exports comptables)

En passant par le lien ci-dessous, vous bénéficiez de 2 mois offerts sur le plan {$planLabel} ({$planPrice} FCFA/mois ensuite, sans engagement).

Activer mon compte Fayeku :
{$link}

Le lien est valable 30 jours. N'hésitez pas à me contacter directement si vous souhaitez en discuter ou si vous avez besoin d'aide pour la mise en route.

Bien cordialement,

{$signatureFull}
{$firmName}
TXT;

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Build the wa.me deep-link with the message URL-encoded as `text` parameter.
     */
    public function buildWhatsAppLink(PartnerInvitation $invitation, string $context = 'invite'): string
    {
        $message = $this->composeWhatsAppMessage($invitation, $context);
        $phone = preg_replace('/\D+/', '', $invitation->invitee_phone ?? '') ?? '';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    /**
     * Build the mailto: deep-link with subject and body URL-encoded.
     */
    public function buildMailtoLink(PartnerInvitation $invitation, string $context = 'invite'): string
    {
        $email = $this->composeEmail($invitation, $context);
        $recipient = trim($invitation->invitee_email ?? '');

        $params = http_build_query([
            'subject' => $email['subject'],
            'body' => $email['body'],
        ], '', '&', PHP_QUERY_RFC3986);

        return 'mailto:'.rawurlencode($recipient).'?'.$params;
    }

    /**
     * Mark an invitation as sent through a given channel.
     */
    public function markSent(PartnerInvitation $invitation, string $channel): void
    {
        $invitation->forceFill([
            'channel' => $channel,
            'status' => $invitation->status === 'accepted' ? 'accepted' : 'pending',
        ])->save();
    }

    /**
     * Mark an invitation as reminded through a given channel.
     */
    public function markReminded(PartnerInvitation $invitation, string $channel): void
    {
        $invitation->forceFill([
            'channel' => $channel,
            'last_reminder_at' => now(),
            'reminder_count' => ($invitation->reminder_count ?? 0) + 1,
        ])->save();
    }

    public function buildInvitationLink(PartnerInvitation $invitation): string
    {
        $invitation->loadMissing('accountantFirm');

        return config('app.url').'/join/'.$invitation->accountantFirm?->invite_code;
    }

    /**
     * Compose the generic partner share message used when copying or sharing the
     * accountant's referral link without targeting a specific PME.
     */
    public function composePartnerShareMessage(Company $firm, ?User $user = null): string
    {
        $firmName = trim($firm->name ?? '') !== '' ? $firm->name : 'notre cabinet';
        $userFirstName = trim($user?->first_name ?? '');
        $link = config('app.url').'/join/'.$firm->invite_code;
        $signature = $userFirstName !== ''
            ? "{$userFirstName}\n{$firmName}"
            : $firmName;

        return <<<TXT
Bonjour,

Je tenais à vous partager *Fayeku*, la plateforme que notre cabinet utilise pour simplifier la facturation et le suivi des paiements de nos clients PME.

En passant par mon lien partenaire, vous bénéficiez de *2 mois offerts* pour démarrer.

Ce que Fayeku change concrètement :
✓ Factures pro en quelques clics, conformes au contexte sénégalais
✓ Relances WhatsApp/Email automatiques sur vos impayés
✓ Vision claire de votre trésorerie à 30 et 90 jours
✓ Collaboration directe avec notre cabinet sur vos pièces

Lien d'inscription: {$link}

Le lien est valable 30 jours. Je reste disponible pour en discuter si besoin.

{$signature}
TXT;
    }

    /**
     * Resolve all template substitutions used by the WhatsApp/Email composers.
     *
     * @return array{0:string,1:string,2:string,3:string,4:string,5:string,6:string,7:string}
     *                                                                                        [contactFirstName, firmName, userFirstName, userLastName, planLabel, planPrice, link, signatureName]
     */
    private function resolveTemplateValues(PartnerInvitation $invitation): array
    {
        $invitation->loadMissing('accountantFirm', 'creator');

        $firmName = $invitation->accountantFirm?->name ?? 'votre cabinet';
        $contactFirstName = $this->firstNameOf($invitation->invitee_name);
        $userFirstName = trim($invitation->creator?->first_name ?? '');
        $userLastName = trim($invitation->creator?->last_name ?? '');
        $plan = $invitation->recommended_plan ?: 'essentiel';
        $planLabel = self::PLAN_LABELS[$plan] ?? self::PLAN_LABELS['essentiel'];
        $planPrice = self::PLAN_PRICES[$plan] ?? self::PLAN_PRICES['essentiel'];
        $link = $this->buildInvitationLink($invitation);
        $signatureName = $userFirstName !== '' ? $userFirstName : $firmName;

        return [
            $contactFirstName,
            $firmName,
            $userFirstName,
            $userLastName,
            $planLabel,
            $planPrice,
            $link,
            $signatureName,
        ];
    }

    private function firstNameOf(?string $fullName): string
    {
        $name = trim($fullName ?? '');
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        return $parts[0] ?? $name;
    }
}
