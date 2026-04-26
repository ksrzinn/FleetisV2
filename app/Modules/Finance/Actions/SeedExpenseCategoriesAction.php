<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Tenancy\Models\Company;

class SeedExpenseCategoriesAction
{
    public function handle(Company $company): void
    {
        foreach (ExpenseCategory::DEFAULTS as $index => $name) {
            ExpenseCategory::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['color' => ExpenseCategory::COLOR_PALETTE[$index % count(ExpenseCategory::COLOR_PALETTE)]]
            );
        }
    }
}
