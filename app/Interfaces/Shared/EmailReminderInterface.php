<?php

namespace App\Interfaces\Shared;

interface EmailReminderInterface
{
    public function send(string $email, string $subject, string $body): bool;
}
