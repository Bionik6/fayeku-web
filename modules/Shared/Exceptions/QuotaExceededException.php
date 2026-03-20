<?php

namespace Modules\Shared\Exceptions;

use Exception;

class QuotaExceededException extends Exception
{
    public function __construct(string $quotaType)
    {
        parent::__construct("Quota exceeded for: {$quotaType}");
    }
}
