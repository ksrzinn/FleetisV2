<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Actions\GenerateInstallmentsAction;
use App\Modules\Finance\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class GenerateInstallmentsActionTest extends TenantTestCase
{
    use RefreshDatabase;

    private GenerateInstallmentsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(GenerateInstallmentsAction::class);
    }

    public function test_one_time_bill_creates_single_installment(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'company_id'   => $user->company_id,
            'supplier'     => 'ACME',
            'bill_type'    => 'one_time',
            'total_amount' => '500.00',
            'due_date'     => '2026-06-01',
        ]);

        $this->assertCount(1, $bill->installments);
        $this->assertEquals('500.00', $bill->installments->first()->amount);
        $this->assertEquals('2026-06-01', $bill->installments->first()->due_date->toDateString());
    }

    public function test_installment_bill_generates_all_n_installments(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'company_id'         => $user->company_id,
            'supplier'           => 'ACME',
            'bill_type'          => 'installment',
            'total_amount'       => '900.00',
            'due_date'           => '2026-06-15',
            'installment_count'  => 3,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 15,
        ]);

        $this->assertCount(3, $bill->installments);
        $this->assertEquals(1, $bill->installments[0]->sequence);
        $this->assertEquals(2, $bill->installments[1]->sequence);
        $this->assertEquals(3, $bill->installments[2]->sequence);
    }

    public function test_installment_bill_splits_amount_evenly_with_remainder_on_last(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'company_id'         => $user->company_id,
            'supplier'           => 'ACME',
            'bill_type'          => 'installment',
            'total_amount'       => '1000.00',
            'due_date'           => '2026-06-15',
            'installment_count'  => 3,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 15,
        ]);

        $installments = $bill->installments;
        $this->assertEquals('333.33', $installments[0]->amount);
        $this->assertEquals('333.33', $installments[1]->amount);
        $this->assertEquals('333.34', $installments[2]->amount);
    }

    public function test_installment_bill_spaces_due_dates_monthly(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        // Use the 15th — mid-month dates are safe from Brazilian holidays
        $bill = $this->action->handle([
            'company_id'         => $user->company_id,
            'supplier'           => 'ACME',
            'bill_type'          => 'installment',
            'total_amount'       => '300.00',
            'due_date'           => '2026-05-15',
            'installment_count'  => 3,
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 15,
        ]);

        $dates = $bill->installments->pluck('due_date')->map->toDateString()->toArray();

        $this->assertEquals('2026-05-15', $dates[0]); // Friday
        $this->assertEquals('2026-06-15', $dates[1]); // Monday
        $this->assertEquals('2026-07-15', $dates[2]); // Wednesday
    }

    public function test_recurring_bill_creates_one_installment_on_store(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'company_id'         => $user->company_id,
            'supplier'           => 'ACME',
            'bill_type'          => 'recurring',
            'total_amount'       => '200.00',
            'due_date'           => '2026-06-10',
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 10,
            'recurrence_end'     => '2027-06-10',
        ]);

        $this->assertCount(1, $bill->installments);
    }

    public function test_recurring_bill_total_amount_is_per_installment(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = $this->action->handle([
            'company_id'         => $user->company_id,
            'supplier'           => 'ACME',
            'bill_type'          => 'recurring',
            'total_amount'       => '350.00',
            'due_date'           => '2026-06-10',
            'recurrence_cadence' => 'monthly',
            'recurrence_day'     => 10,
            'recurrence_end'     => '2027-06-10',
        ]);

        $this->assertEquals('350.00', $bill->installments->first()->amount);
    }
}
