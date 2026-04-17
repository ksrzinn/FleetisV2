<?php

namespace Database\Factories\Fleet;

use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\DriverCompensation;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverCompensationFactory extends Factory
{
    protected $model = DriverCompensation::class;

    public function definition(): array
    {
        return [
            'company_id'     => Company::factory(),
            'driver_id'      => Driver::factory(),
            'type'           => 'percentage',
            'percentage'     => $this->faker->randomFloat(2, 1, 30),
            'fixed_amount'   => null,
            'monthly_salary' => null,
            'effective_from' => now()->toDateString(),
            'effective_to'   => null,
        ];
    }

    public function fixedPerFreight(float $amount = 500.00): static
    {
        return $this->state([
            'type'         => 'fixed_per_freight',
            'percentage'   => null,
            'fixed_amount' => $amount,
        ]);
    }

    public function monthlySalary(float $salary = 3000.00): static
    {
        return $this->state([
            'type'           => 'monthly_salary',
            'percentage'     => null,
            'monthly_salary' => $salary,
        ]);
    }

    public function closed(): static
    {
        return $this->state(['effective_to' => now()->subDay()->toDateString()]);
    }
}
