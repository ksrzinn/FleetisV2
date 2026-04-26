<?php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\Expense;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $expense->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $expense->company_id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $expense->company_id;
    }
}
