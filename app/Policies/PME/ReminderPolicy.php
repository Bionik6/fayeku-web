<?php

namespace App\Policies\PME;

use App\Models\PME\Reminder;
use App\Models\Shared\User;

class ReminderPolicy
{
    public function create(User $user): bool
    {
        return $user->companies()->where('type', 'sme')->exists();
    }

    public function view(User $user, Reminder $reminder): bool
    {
        return $user->companies()
            ->where('companies.id', $reminder->invoice->company_id)
            ->exists();
    }
}
