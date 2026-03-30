<?php

namespace Modules\Compta\Partnership\Services;

use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Shared\Interfaces\WhatsAppProviderInterface;

class InvitationService
{
    public function __construct(
        private WhatsAppProviderInterface $whatsApp,
    ) {}

    public function sendInvitationMessage(PartnerInvitation $invitation): bool
    {
        $message = $this->composeInvitationMessage($invitation);

        return $this->whatsApp->send($invitation->invitee_phone, $message);
    }

    public function sendReminderMessage(PartnerInvitation $invitation): bool
    {
        $message = $this->composeReminderMessage($invitation);

        return $this->whatsApp->send($invitation->invitee_phone, $message);
    }

    public function sendResendMessage(PartnerInvitation $invitation): bool
    {
        return $this->sendInvitationMessage($invitation);
    }

    private function composeInvitationMessage(PartnerInvitation $invitation): string
    {
        $invitation->loadMissing('accountantFirm');
        $firmName = $invitation->accountantFirm?->name ?? 'votre cabinet';
        $link = $this->buildInvitationLink($invitation);

        $name = trim($invitation->invitee_name ?? '');
        $message = "Bonjour {$name}, {$firmName} vous invite à rejoindre Fayeku pour simplifier votre facturation.";

        if ($invitation->recommended_plan === 'essentiel') {
            $message .= ' Profitez de 2 mois offerts pour démarrer.';
        }

        $message .= "\n\n{$link}";

        return $message;
    }

    private function composeReminderMessage(PartnerInvitation $invitation): string
    {
        $invitation->loadMissing('accountantFirm');
        $firmName = $invitation->accountantFirm?->name ?? 'votre cabinet';
        $link = $this->buildInvitationLink($invitation);

        $name = trim($invitation->invitee_name ?? '');

        return "Bonjour {$name}, ceci est un rappel de {$firmName}. Votre invitation à rejoindre Fayeku est toujours active.\n\n{$link}";
    }

    private function buildInvitationLink(PartnerInvitation $invitation): string
    {
        $invitation->loadMissing('accountantFirm');

        return config('app.url').'/join/'.$invitation->accountantFirm?->invite_code;
    }
}
