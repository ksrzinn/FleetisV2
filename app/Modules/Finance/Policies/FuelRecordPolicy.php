<?php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\FuelRecord;

class FuelRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, FuelRecord $fuelRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $fuelRecord->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, FuelRecord $fuelRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $fuelRecord->company_id;
    }

    public function delete(User $user, FuelRecord $fuelRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $fuelRecord->company_id;
    }
}
