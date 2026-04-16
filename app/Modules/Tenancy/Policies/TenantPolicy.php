<?php

namespace App\Modules\Tenancy\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class TenantPolicy
{
    public function belongsToTenant(User $user, Model $model): bool
    {
        return $user->company_id !== null
            && (int) $model->getAttribute('company_id') === (int) $user->company_id;
    }
}
