<?php

namespace Modules\Shared\Interfaces;

interface EmailReminderInterface
{
    public function send(string $email, string $subject, string $body): bool;
}
