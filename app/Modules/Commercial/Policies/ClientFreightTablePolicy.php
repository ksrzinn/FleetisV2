<?php

namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Tenancy\Policies\TenantPolicy;

class ClientFreightTablePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freight_tables.view');
    }

    public function view(User $user, ClientFreightTable $t): bool
    {
        return $user->can('freight_tables.view') && $this->belongsToTenant($user, $t);
    }

    public function create(User $user): bool
    {
        return $user->can('freight_tables.manage');
    }

    public function update(User $user, ClientFreightTable $t): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $t);
    }

    public function delete(User $user, ClientFreightTable $t): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $t);
    }
}
