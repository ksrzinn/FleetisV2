<?php

namespace App\Modules\Fleet\Policies;

use App\Models\User;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Tenancy\Policies\TenantPolicy;

class DriverPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial']);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial'])
            && $this->belongsToTenant($user, $driver);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $driver);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $driver);
    }
}
