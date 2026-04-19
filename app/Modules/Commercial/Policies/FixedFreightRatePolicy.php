<?php

namespace App\Modules\Commercial\Policies;

use App\Models\User;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Tenancy\Policies\TenantPolicy;

class FixedFreightRatePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freight_tables.view');
    }

    public function create(User $user): bool
    {
        return $user->can('freight_tables.manage');
    }

    public function update(User $user, FixedFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }

    public function delete(User $user, FixedFreightRate $r): bool
    {
        return $user->can('freight_tables.manage') && $this->belongsToTenant($user, $r);
    }
}
