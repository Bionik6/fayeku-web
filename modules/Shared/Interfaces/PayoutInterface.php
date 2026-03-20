<?php

namespace Modules\Shared\Interfaces;

interface PayoutInterface
{
    public function send(string $phone, int $amountFcfa, string $reference): bool;
}
