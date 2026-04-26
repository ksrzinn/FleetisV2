<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use App\Modules\Operations\Models\FreightStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class RecordPaymentTest extends TenantTestCase
{
    use RefreshDatabase;

    private function postPayment(mixed $user, Receivable $receivable, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsTenant($user)->post(
            "/receivables/{$receivable->id}/payments",
            $data
        );
    }

    // ── Partial payment ──────────────────────────────────────────────────────

    public function test_partial_payment_creates_payment_record(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'amount_due' => '1000.00',
            'amount_paid' => '0.00',
            'status' => 'open',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 400.00,
            'method' => 'pix',
        ]);

        $this->assertDatabaseHas('payments', [
            'payable_type' => 'receivable',
            'payable_id'   => $receivable->id,
            'amount'       => '400.00',
            'method'       => 'pix',
        ]);
    }

    public function test_partial_payment_sets_receivable_to_partially_paid(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 400.00,
            'method' => 'pix',
        ]);

        $this->assertDatabaseHas('receivables', [
            'id'          => $receivable->id,
            'amount_paid' => '400.00',
            'status'      => 'partially_paid',
        ]);
    }

    public function test_second_partial_payment_accumulates_amount_paid(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '400.00',
            'status'      => 'partially_paid',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 300.00,
            'method' => 'transferencia',
        ]);

        $this->assertDatabaseHas('receivables', [
            'id'          => $receivable->id,
            'amount_paid' => '700.00',
            'status'      => 'partially_paid',
        ]);
    }

    // ── Full payment ─────────────────────────────────────────────────────────

    public function test_full_payment_sets_receivable_to_paid(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 1000.00,
            'method' => 'boleto',
        ]);

        $this->assertDatabaseHas('receivables', [
            'id'          => $receivable->id,
            'amount_paid' => '1000.00',
            'status'      => 'paid',
        ]);
    }

    public function test_full_payment_transitions_linked_freight_to_completed(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '1000.00',
        ]);
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'freight_id'  => $freight->id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 1000.00,
            'method' => 'pix',
        ]);

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull($freight->fresh()->completed_at);
    }

    public function test_full_payment_appends_pt_br_note_to_freight_status_history(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->awaitingPayment()->create([
            'company_id'    => $user->company_id,
            'freight_value' => '1000.00',
        ]);
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'freight_id'  => $freight->id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 1000.00,
            'method' => 'pix',
        ]);

        $this->assertDatabaseHas('freight_status_history', [
            'freight_id'  => $freight->id,
            'from_status' => 'awaiting_payment',
            'to_status'   => 'completed',
            'notes'       => 'Frete concluído automaticamente após pagamento integral.',
        ]);
    }

    public function test_final_partial_payment_that_reaches_full_amount_marks_paid(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '600.00',
            'status'      => 'partially_paid',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 400.00,
            'method' => 'dinheiro',
        ]);

        $this->assertDatabaseHas('receivables', [
            'id'          => $receivable->id,
            'amount_paid' => '1000.00',
            'status'      => 'paid',
        ]);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_payment_amount_exceeding_remaining_balance_is_rejected(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '800.00',
            'status'      => 'partially_paid',
        ]);

        $response = $this->postPayment($user, $receivable, [
            'amount' => 500.00, // remaining is only 200
            'method' => 'pix',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('payments', ['payable_id' => $receivable->id]);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
        ]);

        $response = $this->postPayment($user, $receivable, [
            'amount' => 0,
            'method' => 'pix',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_payment_method_is_required(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
        ]);

        $response = $this->postPayment($user, $receivable, [
            'amount' => 200.00,
        ]);

        $response->assertSessionHasErrors('method');
    }

    public function test_invalid_payment_method_is_rejected(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
        ]);

        $response = $this->postPayment($user, $receivable, [
            'amount' => 200.00,
            'method' => 'bitcoin',
        ]);

        $response->assertSessionHasErrors('method');
    }

    public function test_payment_accepts_optional_notes(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '0.00',
        ]);

        $this->postPayment($user, $receivable, [
            'amount' => 200.00,
            'method' => 'pix',
            'notes'  => 'Parcela referente à NF 001',
        ]);

        $this->assertDatabaseHas('payments', [
            'payable_id' => $receivable->id,
            'notes'      => 'Parcela referente à NF 001',
        ]);
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_operator_cannot_record_payment(): void
    {
        $operator = $this->makeUserWithRole('Operator');
        $financial = $this->makeUserWithRole('Financial', $operator->company);
        $receivable = Receivable::factory()->create(['company_id' => $operator->company_id]);

        $response = $this->postPayment($operator, $receivable, [
            'amount' => 200.00,
            'method' => 'pix',
        ]);

        $response->assertForbidden();
    }

    public function test_payment_cannot_be_recorded_on_already_paid_receivable(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'amount_due'  => '1000.00',
            'amount_paid' => '1000.00',
            'status'      => 'paid',
        ]);

        $response = $this->postPayment($user, $receivable, [
            'amount' => 100.00,
            'method' => 'pix',
        ]);

        $response->assertSessionHasErrors('amount');
    }
}
