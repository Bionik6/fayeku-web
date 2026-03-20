<?php

namespace Modules\Compta\Compliance\DTOs;

final class FiscalCertification
{
    public function __construct(
        public readonly string $reference,
        public readonly string $token,
        public readonly ?int   $balanceSticker,
        public readonly array  $rawResponse,
    ) {}
}
