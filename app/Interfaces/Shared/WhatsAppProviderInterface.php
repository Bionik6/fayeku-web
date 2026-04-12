<?php

namespace App\Interfaces\Shared;

interface WhatsAppProviderInterface
{
    public function send(string $phone, string $message): bool;
}
