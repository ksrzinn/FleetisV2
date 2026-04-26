<?php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillInstallment> */
class BillInstallmentFactory extends Factory
{
    protected $model = BillInstallment::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $bill    = Bill::factory()->create(['company_id' => $company->id]);

        return [
            'company_id'  => $company->id,
            'bill_id'     => $bill->id,
            'sequence'    => 1,
            'amount'      => fake()->randomFloat(2, 100, 2000),
            'due_date'    => now()->addDays(30)->toDateString(),
            'paid_amount' => null,
            'paid_at'     => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'paid_amount' => $attrs['amount'],
            'paid_at'     => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'due_date'    => now()->subDays(5)->toDateString(),
            'paid_amount' => null,
        ]);
    }
}
