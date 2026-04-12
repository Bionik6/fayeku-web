<?php

namespace App\Enums\Shared;

enum QuotaType: string
{
    case Reminders = 'reminders';
    case Users = 'users';
    case Clients = 'clients';
    case StorageMb = 'storage_mb';
}
