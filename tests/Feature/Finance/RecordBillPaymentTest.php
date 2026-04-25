<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Actions\RecordBillPaymentAction;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class RecordBillPaymentTest extends TenantTestCase
{
    use RefreshDatabase;

    private RecordBillPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(RecordBillPaymentAction::class);
    }

    private function makeInstallment(array $attrs = []): BillInstallment
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);

        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        return BillInstallment::factory()->create(array_merge([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'amount'     => '1000.00',
        ], $attrs));
    }

    public function test_partial_payment_sets_partially_paid_status(): void
    {
        $installment = $this->makeInstallment(['amount' => '1000.00']);

        $this->action->handle($installment, [
            'amount'  => '400.00',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $installment->refresh();

        $this->assertEquals('400.00', $installment->paid_amount);
        $this->assertEquals('partially_paid', $installment->status);
        $this->assertNull($installment->paid_at);
    }

    public function test_full_payment_sets_paid_status_and_paid_at(): void
    {
        $installment = $this->makeInstallment(['amount' => '500.00']);

        $this->action->handle($installment, [
            'amount'  => '500.00',
            'method'  => 'boleto',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $installment->refresh();

        $this->assertEquals('500.00', $installment->paid_amount);
        $this->assertEquals('paid', $installment->status);
        $this->assertNotNull($installment->paid_at);
    }

    public function test_payment_record_is_persisted_with_bill_installment_type(): void
    {
        $installment = $this->makeInstallment(['amount' => '800.00']);

        $this->action->handle($installment, [
            'amount'  => '200.00',
            'method'  => 'transferencia',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('payments', [
            'payable_type' => 'bill_installment',
            'payable_id'   => $installment->id,
            'amount'       => '200.00',
        ]);
    }

    public function test_cumulative_partial_payments_eventually_mark_paid(): void
    {
        $installment = $this->makeInstallment(['amount' => '1000.00']);

        $this->action->handle($installment, [
            'amount'  => '600.00',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $installment->refresh();

        $this->action->handle($installment, [
            'amount'  => '400.00',
            'method'  => 'dinheiro',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $installment->refresh();

        $this->assertEquals('1000.00', $installment->paid_amount);
        $this->assertEquals('paid', $installment->status);
        $this->assertNotNull($installment->paid_at);
    }
}
