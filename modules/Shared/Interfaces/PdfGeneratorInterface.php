<?php

namespace Modules\Shared\Interfaces;

interface PdfGeneratorInterface
{
    public function generate(string $view, array $data): string;
}
