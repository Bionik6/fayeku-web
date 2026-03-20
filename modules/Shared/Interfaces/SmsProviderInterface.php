<?php

namespace Modules\Shared\Interfaces;

interface SmsProviderInterface
{
    public function send(string $phone, string $message): bool;
}
