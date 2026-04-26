<?php

namespace App\Modules\Finance\Policies;

use App\Models\User;

class ExpenseCategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }
}
