<?php

namespace Database\Factories\Finance;

use App\Modules\Commercial\Models\Client;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receivable> */
class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id'  => $company->id,
            'client_id'   => Client::factory()->create(['company_id' => $company->id])->id,
            'freight_id'  => null,
            'amount_due'  => fake()->randomFloat(2, 100, 5000),
            'amount_paid' => '0.00',
            'due_date'    => now()->addDays(30)->toDateString(),
            'status'      => 'open',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'amount_paid' => $attrs['amount_due'],
            'status'      => 'paid',
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'due_date' => now()->subDays(5)->toDateString(),
            'status'   => 'overdue',
        ]);
    }
}
