<?php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCategory> */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name'       => $this->faker->unique()->word(),
            'color'      => $this->faker->randomElement(ExpenseCategory::COLOR_PALETTE),
        ];
    }
}
