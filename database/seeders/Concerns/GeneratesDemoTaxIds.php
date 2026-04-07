<?php

namespace Database\Seeders\Concerns;

trait GeneratesDemoTaxIds
{
    protected function demoTaxId(string $countryCode = 'SN'): string
    {
        $digits = '';

        for ($index = 0; $index < 10; $index++) {
            $digits .= (string) random_int(0, 9);
        }

        return strtoupper($countryCode).$digits;
    }
}
