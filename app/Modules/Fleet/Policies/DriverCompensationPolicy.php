<?php

namespace App\Modules\Fleet\Policies;

use App\Models\User;
use App\Modules\Fleet\Models\DriverCompensation;
use App\Modules\Tenancy\Policies\TenantPolicy;

class DriverCompensationPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial']);
    }

    public function view(User $user, DriverCompensation $compensation): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator', 'Financial'])
            && $this->belongsToTenant($user, $compensation);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Operator']);
    }
}
