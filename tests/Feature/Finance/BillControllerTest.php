<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\BillInstallment;
use App\Modules\Finance\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class BillControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_financial_can_access_bills_index(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->get('/bills');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Finance/Bills/Index'));
    }

    public function test_operator_cannot_access_bills(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->get('/bills');

        $response->assertForbidden();
    }

    public function test_index_only_returns_own_company_bills(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Bill::factory()->create(['company_id' => $userA->company_id]);
        Bill::factory()->create(['company_id' => $userA->company_id]);
        Bill::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->get('/bills');

        $response->assertInertia(fn ($page) => $page->has('bills.data', 2));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function test_financial_can_create_one_time_bill(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->post('/bills', [
            'supplier'     => 'Fornecedor LTDA',
            'bill_type'    => 'one_time',
            'total_amount' => '500.00',
            'due_date'     => '2026-07-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['supplier' => 'Fornecedor LTDA', 'company_id' => $user->company_id]);
        $this->assertDatabaseHas('bill_installments', ['company_id' => $user->company_id, 'sequence' => 1]);
    }

    public function test_operator_cannot_create_bill(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->post('/bills', [
            'supplier'     => 'Fornecedor LTDA',
            'bill_type'    => 'one_time',
            'total_amount' => '500.00',
            'due_date'     => '2026-07-01',
        ]);

        $response->assertForbidden();
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_financial_can_view_bill(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);
        BillInstallment::factory()->create(['company_id' => $user->company_id, 'bill_id' => $bill->id]);

        $response = $this->actingAsTenant($user)->get("/bills/{$bill->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Finance/Bills/Show')
            ->has('bill')
            ->has('bill.installments')
        );
    }

    public function test_financial_cannot_view_other_company_bill(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');
        $bill  = Bill::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->get("/bills/{$bill->id}");

        // BelongsToCompany scope returns 404
        $response->assertNotFound();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_financial_can_update_bill_supplier(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id, 'supplier' => 'Old Name']);

        $response = $this->actingAsTenant($user)->put("/bills/{$bill->id}", [
            'supplier'     => 'New Name',
            'bill_type'    => $bill->bill_type,
            'total_amount' => $bill->total_amount,
            'due_date'     => $bill->due_date->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'supplier' => 'New Name']);
    }

    public function test_financial_cannot_update_other_company_bill(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');
        $bill  = Bill::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->put("/bills/{$bill->id}", [
            'supplier' => 'Hacked',
        ]);

        $response->assertNotFound();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_financial_can_delete_bill_without_payments(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $bill = Bill::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->delete("/bills/{$bill->id}");

        $response->assertRedirect('/bills');
        $this->assertSoftDeleted('bills', ['id' => $bill->id]);
    }

    public function test_financial_cannot_delete_bill_with_payments(): void
    {
        $user        = $this->makeUserWithRole('Financial');
        $bill        = Bill::factory()->create(['company_id' => $user->company_id]);
        $installment = BillInstallment::factory()->create([
            'company_id'  => $user->company_id,
            'bill_id'     => $bill->id,
            'paid_amount' => '100.00',
        ]);

        $response = $this->actingAsTenant($user)->delete("/bills/{$bill->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted('bills', ['id' => $bill->id]);
    }

    public function test_operator_cannot_delete_bill(): void
    {
        $operator  = $this->makeUserWithRole('Operator');
        $financial = $this->makeUserWithRole('Financial', $operator->company);
        $bill      = Bill::factory()->create(['company_id' => $operator->company_id]);

        $response = $this->actingAsTenant($operator)->delete("/bills/{$bill->id}");

        $response->assertForbidden();
    }

    // ── Payment recording ─────────────────────────────────────────────────────

    public function test_financial_can_record_installment_payment(): void
    {
        $user        = $this->makeUserWithRole('Financial');
        $bill        = Bill::factory()->create(['company_id' => $user->company_id]);
        $installment = BillInstallment::factory()->create([
            'company_id' => $user->company_id,
            'bill_id'    => $bill->id,
            'amount'     => '500.00',
        ]);

        $response = $this->actingAsTenant($user)->post("/bill-installments/{$installment->id}/payments", [
            'amount'  => '500.00',
            'method'  => 'pix',
            'paid_at' => '2026-06-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'payable_type' => 'bill_installment',
            'payable_id'   => $installment->id,
        ]);
        $this->assertDatabaseHas('bill_installments', [
            'id'          => $installment->id,
            'paid_amount' => '500.00',
        ]);
    }

    public function test_operator_cannot_record_installment_payment(): void
    {
        $operator    = $this->makeUserWithRole('Operator');
        $financial   = $this->makeUserWithRole('Financial', $operator->company);
        $bill        = Bill::factory()->create(['company_id' => $operator->company_id]);
        $installment = BillInstallment::factory()->create([
            'company_id' => $operator->company_id,
            'bill_id'    => $bill->id,
        ]);

        $response = $this->actingAsTenant($operator)->post("/bill-installments/{$installment->id}/payments", [
            'amount'  => '100.00',
            'method'  => 'pix',
            'paid_at' => '2026-06-01',
        ]);

        $response->assertForbidden();
    }
}
