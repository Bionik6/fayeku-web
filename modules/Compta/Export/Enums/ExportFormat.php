<?php

namespace Modules\Compta\Export\Enums;

enum ExportFormat: string
{
    case Sage100 = 'sage100';
    case Ebp = 'ebp';
    case Excel = 'excel';
}
