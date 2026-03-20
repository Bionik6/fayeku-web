<?php

namespace Modules\PME\Collection\Policies;

use Modules\PME\Collection\Models\Reminder;
use Modules\Shared\Models\User;

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
