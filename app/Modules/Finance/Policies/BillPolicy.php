<?php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function view(User $user, Bill $bill): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $bill->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial']);
    }

    public function update(User $user, Bill $bill): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $bill->company_id;
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $bill->company_id;
    }

    public function recordPayment(User $user, BillInstallment $installment): bool
    {
        return $user->hasAnyRole(['Admin', 'Financial'])
            && $user->company_id === $installment->company_id;
    }
}
