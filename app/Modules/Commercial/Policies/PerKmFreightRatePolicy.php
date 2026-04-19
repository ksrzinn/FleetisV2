<?php

namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\PerKmFreightRate;
use App\Modules\Tenancy\Policies\TenantPolicy;

class PerKmFreightRatePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freight_tables.view');
    }

    public function view(User $user, PerKmFreightRate $r): bool
    {
        return $user->can('freight_tables.view') && $this->belongsToTenant($user, $r);
    }

    public function create(User $user): bool
    {
        return $user->can('freight_tables.manage');
    }

    public function update(User $user, PerKmFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }

    public function delete(User $user, PerKmFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }
}
