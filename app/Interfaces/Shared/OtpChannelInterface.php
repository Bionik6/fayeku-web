<?php

namespace App\Interfaces\Shared;

interface OtpChannelInterface
{
    /**
     * Envoie un code OTP à un numéro de téléphone via le canal concret
     * (WhatsApp template, SMS Orange, etc.). Le canal est responsable du
     * rendu du message et de la livraison.
     */
    public function send(string $phone, string $code): bool;
}
