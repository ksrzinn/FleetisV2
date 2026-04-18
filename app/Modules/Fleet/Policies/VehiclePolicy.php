<?php

namespace App\Modules\Fleet\Policies;

use App\Models\User;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Tenancy\Policies\TenantPolicy;

class VehiclePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial']);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial'])
            && $this->belongsToTenant($user, $vehicle);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator'])
            && $this->belongsToTenant($user, $vehicle);
    }
}
