<?php

namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\Client;
use App\Modules\Tenancy\Policies\TenantPolicy;

class ClientPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can('clients.view') && $this->belongsToTenant($user, $client);
    }

    public function create(User $user): bool
    {
        return $user->can('clients.manage');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('clients.manage') && $this->belongsToTenant($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.delete') && $this->belongsToTenant($user, $client);
    }
}
