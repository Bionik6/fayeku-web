<?php

namespace App\Interfaces\Shared;

interface PayoutInterface
{
    public function send(string $phone, int $amountFcfa, string $reference): bool;
}
