<?php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bill> */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'company_id'         => Company::factory(),
            'supplier'           => fake()->company(),
            'description'        => fake()->sentence(),
            'bill_type'          => 'one_time',
            'total_amount'       => fake()->randomFloat(2, 100, 5000),
            'due_date'           => now()->addDays(30)->toDateString(),
            'recurrence_cadence' => null,
            'recurrence_day'     => null,
            'recurrence_end'     => null,
            'installment_count'  => null,
        ];
    }

    public function recurring(string $cadence = 'monthly'): static
    {
        return $this->state([
            'bill_type'          => 'recurring',
            'recurrence_cadence' => $cadence,
            'recurrence_day'     => 10,
            'recurrence_end'     => now()->addYear()->toDateString(),
        ]);
    }

    public function installment(int $count = 3): static
    {
        return $this->state([
            'bill_type'          => 'installment',
            'installment_count'  => $count,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => now()->addMonth()->day,
        ]);
    }
}
