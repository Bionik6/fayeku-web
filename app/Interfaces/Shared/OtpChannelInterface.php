<?php

namespace App\Interfaces\Shared;

interface OtpChannelInterface
{
    /**
     * Envoie un code OTP à un destinataire via le canal concret
     * (email, WhatsApp template, SMS Orange, etc.). L'identifiant est
     * interprété selon le canal : email, téléphone normalisé, etc.
     */
    public function send(string $identifier, string $code): bool;
}
