<?php

namespace App\Interfaces\Shared;

interface PdfGeneratorInterface
{
    public function generate(string $view, array $data): string;
}
