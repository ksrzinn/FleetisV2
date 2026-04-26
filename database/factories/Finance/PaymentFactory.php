<?php

namespace Database\Factories\Finance;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $receivable = Receivable::factory()->create(['company_id' => $company->id]);

        return [
            'company_id'   => $company->id,
            'payable_type' => 'receivable',
            'payable_id'   => $receivable->id,
            'amount'       => fake()->randomFloat(2, 50, 1000),
            'paid_at'      => now(),
            'method'       => fake()->randomElement(['pix', 'transferencia', 'dinheiro', 'cheque', 'boleto']),
            'notes'        => null,
        ];
    }
}
