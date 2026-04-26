<?php

namespace App\Modules\Operations\Policies;

use App\Models\User;
use App\Modules\Operations\Models\Freight;
use App\Modules\Tenancy\Policies\TenantPolicy;

class FreightPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial']);
    }

    public function view(User $user, Freight $freight): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial'])
            && $this->belongsToTenant($user, $freight);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }

    public function update(User $user, Freight $freight): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $freight);
    }

    public function delete(User $user, Freight $freight): bool
    {
        return $user->hasRole('Admin')
            && $this->belongsToTenant($user, $freight);
    }

    public function transition(User $user, Freight $freight): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $freight);
    }
}
