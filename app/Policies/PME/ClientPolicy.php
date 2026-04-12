<?php

namespace App\Policies\PME;

use App\Models\PME\Client;
use App\Models\Shared\User;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        return $user->companies()->where('companies.id', $client->company_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->companies()->where('type', 'sme')->exists();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->companies()->where('companies.id', $client->company_id)->exists();
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
