<?php

namespace Modules\Shared\Interfaces;

interface WhatsAppProviderInterface
{
    public function send(string $phone, string $message): bool;
}
