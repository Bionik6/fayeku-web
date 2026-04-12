<?php

namespace App\Interfaces\Shared;

interface SmsProviderInterface
{
    public function send(string $phone, string $message): bool;
}
