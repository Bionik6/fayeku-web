<?php

namespace App\Enums\Compta;

enum ExportFormat: string
{
    case Sage100 = 'sage100';
    case Ebp = 'ebp';
    case Excel = 'excel';
}
