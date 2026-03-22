<?php

namespace Modules\Compta\Compliance\DTOs;

use Modules\Compta\Compliance\Enums\CertificationAuthority;

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
