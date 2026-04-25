<?php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\MaintenanceRecord;

class MaintenanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $maintenanceRecord->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $maintenanceRecord->company_id;
    }

    public function delete(User $user, MaintenanceRecord $maintenanceRecord): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $maintenanceRecord->company_id;
    }
}
