<?php

namespace Modules\Shared\Enums;

enum QuotaType: string
{
    case Reminders = 'reminders';
    case Users     = 'users';
    case Clients   = 'clients';
    case StorageMb = 'storage_mb';
}
