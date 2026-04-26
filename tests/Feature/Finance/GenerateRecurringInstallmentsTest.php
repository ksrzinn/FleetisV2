<?php

namespace Tests\Feature\Finance;

use App\Console\Commands\Finance\GenerateRecurringBillInstallmentsCommand;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

class GenerateRecurringInstallmentsTest extends TenantTestCase
{
    use RefreshDatabase;

    private function makeRecurringBill(array $billAttrs = [], array $installmentAttrs = []): Bill
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = Bill::factory()->recurring()->create(array_merge([
            'company_id'     => $user->company_id,
            'total_amount'   => '300.00',
            'recurrence_day' => 10,
        ], $billAttrs));

        BillInstallment::factory()->create(array_merge([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'sequence'   => 1,
            'amount'     => '300.00',
        ], $installmentAttrs));

        return $bill;
    }

    public function test_generates_next_installment_when_last_due_date_plus_cadence_is_today(): void
    {
        // Last installment due 2 months ago — next due is 1 month ago (in the past), so it should generate
        $bill = $this->makeRecurringBill([], [
            'due_date' => now()->subMonths(2)->toDateString(),
        ]);

        Artisan::call('finance:generate-recurring-installments');

        $this->assertEquals(2, $bill->installments()->count());
    }

    public function test_does_not_generate_when_next_due_date_is_in_the_future(): void
    {
        // Last installment due in the future → next would be even further out
        $bill = $this->makeRecurringBill([], [
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        Artisan::call('finance:generate-recurring-installments');

        $this->assertEquals(1, $bill->installments()->count());
    }

    public function test_idempotent_when_installment_for_date_already_exists(): void
    {
        $bill = $this->makeRecurringBill([], [
            'due_date' => now()->subMonths(2)->toDateString(),
        ]);

        Artisan::call('finance:generate-recurring-installments');
        Artisan::call('finance:generate-recurring-installments');

        $this->assertEquals(2, $bill->installments()->count());
    }

    public function test_stops_generation_past_recurrence_end(): void
    {
        // Last installment 20 days ago; next due = ~10 days from now; recurrence_end = 2 days ago
        // nextDue > recurrence_end → command must skip
        $bill = $this->makeRecurringBill(
            ['recurrence_end' => now()->subDays(2)->toDateString()],
            ['due_date' => now()->subDays(20)->toDateString()]
        );

        Artisan::call('finance:generate-recurring-installments');

        $this->assertEquals(1, $bill->installments()->count());
    }

    public function test_soft_deleted_bills_are_skipped(): void
    {
        $bill = $this->makeRecurringBill([], [
            'due_date' => now()->subMonth()->toDateString(),
        ]);

        $bill->delete();

        Artisan::call('finance:generate-recurring-installments');

        // withTrashed to check: should still be 1 even after delete
        $this->assertEquals(1, BillInstallment::withoutGlobalScopes()->where('bill_id', $bill->id)->count());
    }
}
