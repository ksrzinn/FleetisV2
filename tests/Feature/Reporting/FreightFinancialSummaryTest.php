<?php

namespace Tests\Feature\Reporting;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FreightFinancialSummaryTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_freight_show_includes_financial_summary_prop(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('financialSummary'));
    }

    public function test_financial_summary_includes_linked_receivable(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        Receivable::factory()->create([
            'company_id' => $user->company_id,
            'freight_id' => $freight->id,
            'amount_due' => '1500.00',
            'status'     => 'open',
        ]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p
                ->where('financialSummary.receivable.amount_due', '1500.00')
                ->where('financialSummary.receivable.status', 'open')
            );
    }

    public function test_financial_summary_includes_linked_expenses(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        Expense::factory()->create([
            'company_id' => $user->company_id,
            'freight_id' => $freight->id,
            'amount'     => '250.00',
        ]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p->has('financialSummary.expenses', 1));
    }

    public function test_financial_summary_includes_linked_fuel_records(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        FuelRecord::factory()->create([
            'company_id' => $user->company_id,
            'freight_id' => $freight->id,
            'total_cost' => '180.00',
        ]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p->has('financialSummary.fuel_records', 1));
    }

    public function test_financial_summary_receivable_is_null_when_no_receivable_linked(): void
    {
        $user    = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->get("/freights/{$freight->id}")
            ->assertInertia(fn ($p) => $p
                ->where('financialSummary.receivable', null)
            );
    }
}
