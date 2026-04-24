<?php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\Receivable;

class ReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, Receivable $receivable): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $receivable->company_id;
    }

    public function recordPayment(User $user, Receivable $receivable): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $receivable->company_id;
    }
}
