<?php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id'          => $company->id,
            'expense_category_id' => ExpenseCategory::factory()->create(['company_id' => $company->id])->id,
            'amount'              => $this->faker->randomFloat(2, 10, 5000),
            'incurred_on'         => now()->toDateString(),
            'description'         => null,
            'vehicle_id'          => null,
            'freight_id'          => null,
        ];
    }
}
