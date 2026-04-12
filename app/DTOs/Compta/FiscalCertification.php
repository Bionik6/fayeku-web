<?php

namespace App\DTOs\Compta;

use App\Enums\Compta\CertificationAuthority;

final class FiscalCertification
{
    public function __construct(
        public readonly CertificationAuthority $authority,
        public readonly string $reference,
        public readonly string $token,
        public readonly ?int $balanceSticker,
        public readonly array $rawResponse,
    ) {}
}
