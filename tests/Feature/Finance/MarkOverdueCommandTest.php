<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\Receivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

class MarkOverdueCommandTest extends TenantTestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_past_due_open_receivable_is_marked_overdue(): void
    {
        Carbon::setTestNow('2026-05-01');
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'due_date'   => '2026-04-30',
            'status'     => 'open',
        ]);

        $this->artisan('finance:mark-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('receivables', [
            'id'     => $receivable->id,
            'status' => 'overdue',
        ]);
    }

    public function test_past_due_partially_paid_receivable_is_marked_overdue(): void
    {
        Carbon::setTestNow('2026-05-01');
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'due_date'    => '2026-04-30',
            'status'      => 'partially_paid',
            'amount_paid' => '200.00',
        ]);

        $this->artisan('finance:mark-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('receivables', [
            'id'     => $receivable->id,
            'status' => 'overdue',
        ]);
    }

    public function test_future_due_receivable_is_not_touched(): void
    {
        Carbon::setTestNow('2026-05-01');
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'due_date'   => '2026-05-15',
            'status'     => 'open',
        ]);

        $this->artisan('finance:mark-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('receivables', [
            'id'     => $receivable->id,
            'status' => 'open',
        ]);
    }

    public function test_due_today_is_not_overdue(): void
    {
        Carbon::setTestNow('2026-05-01');
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'due_date'   => '2026-05-01',
            'status'     => 'open',
        ]);

        $this->artisan('finance:mark-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('receivables', [
            'id'     => $receivable->id,
            'status' => 'open',
        ]);
    }

    public function test_paid_receivable_is_never_marked_overdue(): void
    {
        Carbon::setTestNow('2026-05-01');
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'due_date'   => '2026-04-01',
            'status'     => 'paid',
        ]);

        $this->artisan('finance:mark-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('receivables', [
            'id'     => $receivable->id,
            'status' => 'paid',
        ]);
    }

    public function test_already_overdue_receivable_remains_overdue(): void
    {
        Carbon::setTestNow('2026-05-01');
        $user = $this->makeUserWithRole('Financial');
        $receivable = Receivable::factory()->create([
            'company_id' => $user->company_id,
            'due_date'   => '2026-04-01',
            'status'     => 'overdue',
        ]);

        $this->artisan('finance:mark-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('receivables', [
            'id'     => $receivable->id,
            'status' => 'overdue',
        ]);
    }

    public function test_command_affects_all_tenants(): void
    {
        Carbon::setTestNow('2026-05-01');
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $receivableA = Receivable::factory()->create([
            'company_id' => $userA->company_id,
            'due_date'   => '2026-04-30',
            'status'     => 'open',
        ]);
        $receivableB = Receivable::factory()->create([
            'company_id' => $userB->company_id,
            'due_date'   => '2026-04-30',
            'status'     => 'open',
        ]);

        $this->artisan('finance:mark-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('receivables', ['id' => $receivableA->id, 'status' => 'overdue']);
        $this->assertDatabaseHas('receivables', ['id' => $receivableB->id, 'status' => 'overdue']);
    }
}
