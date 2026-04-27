<?php

namespace Tests\Feature\Reporting;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class VehicleReportControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_financial_can_access_vehicle_report_index(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $this->actingAsTenant($user)->get('/reports/vehicles')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reporting/Vehicles'));
    }

    public function test_admin_can_access_vehicle_report_index(): void
    {
        $user = $this->makeUserWithRole('Admin');

        $this->actingAsTenant($user)->get('/reports/vehicles')->assertOk();
    }

    public function test_operator_cannot_access_vehicle_report(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $this->actingAsTenant($user)->get('/reports/vehicles')->assertForbidden();
    }

    // ── Vehicle list scoping ────────────────────────────────────────────────────

    public function test_vehicle_report_index_only_includes_own_company_vehicles(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        Vehicle::factory()->create(['company_id' => $userA->company_id, 'kind' => 'vehicle']);
        Vehicle::factory()->create(['company_id' => $userA->company_id, 'kind' => 'vehicle']);
        Vehicle::factory()->create(['company_id' => $userB->company_id, 'kind' => 'vehicle']);

        $this->actingAsTenant($userA)->get('/reports/vehicles')
            ->assertInertia(fn ($p) => $p->has('vehicles', 2));
    }

    // ── Per-vehicle show ────────────────────────────────────────────────────────

    public function test_vehicle_show_returns_correct_component_and_metrics(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'kind' => 'vehicle']);

        Freight::factory()->create([
            'company_id'    => $user->company_id,
            'vehicle_id'    => $vehicle->id,
            'freight_value' => '2000.00',
            'finished_at'   => now(),
        ]);
        FuelRecord::factory()->create([
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'total_cost' => '300.00',
        ]);
        MaintenanceRecord::factory()->create([
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'cost'       => '500.00',
        ]);

        $this->actingAsTenant($user)->get("/reports/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Reporting/VehicleShow')
                ->where('metrics.revenue', '2000.00')
                ->where('metrics.fuel_cost', '300.00')
                ->where('metrics.maintenance_cost', '500.00')
                ->where('metrics.freight_count', 1)
            );
    }

    public function test_vehicle_show_is_scoped_to_own_company(): void
    {
        $userA   = $this->makeUserWithRole('Financial');
        $userB   = $this->makeUserWithRole('Financial');
        $vehicleB = Vehicle::factory()->create(['company_id' => $userB->company_id, 'kind' => 'vehicle']);

        $this->actingAsTenant($userA)->get("/reports/vehicles/{$vehicleB->id}")
            ->assertForbidden();
    }

    // ── Fretes a Receber metric ─────────────────────────────────────────────────

    public function test_vehicle_report_index_includes_freights_receivable_outstanding_prop(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $this->actingAsTenant($user)->get('/reports/vehicles')
            ->assertInertia(fn ($p) => $p->has('freightsReceivableOutstanding'));
    }

    public function test_freights_receivable_outstanding_sums_unpaid_freight_receivables(): void
    {
        $user = $this->makeUserWithRole('Financial');

        // Partially paid receivable — outstanding = 1500 - 500 = 1000
        $freightA = Freight::factory()->create(['company_id' => $user->company_id]);
        Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'freight_id'  => $freightA->id,
            'amount_due'  => '1500.00',
            'amount_paid' => '500.00',
            'status'      => 'partially_paid',
        ]);

        // Open receivable — outstanding = 800
        $freightB = Freight::factory()->create(['company_id' => $user->company_id]);
        Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'freight_id'  => $freightB->id,
            'amount_due'  => '800.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        // Fully paid — must NOT be included
        $freightC = Freight::factory()->create(['company_id' => $user->company_id]);
        Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'freight_id'  => $freightC->id,
            'amount_due'  => '2000.00',
            'amount_paid' => '2000.00',
            'status'      => 'paid',
        ]);

        // Total outstanding = 1000 + 800 = 1800
        $this->actingAsTenant($user)->get('/reports/vehicles')
            ->assertInertia(fn ($p) => $p
                ->where('freightsReceivableOutstanding', fn ($v) => (float) $v === 1800.0)
            );
    }

    public function test_freights_receivable_outstanding_is_scoped_to_own_company(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        $freightA = Freight::factory()->create(['company_id' => $userA->company_id]);
        Receivable::factory()->create([
            'company_id'  => $userA->company_id,
            'freight_id'  => $freightA->id,
            'amount_due'  => '600.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $freightB = Freight::factory()->create(['company_id' => $userB->company_id]);
        Receivable::factory()->create([
            'company_id'  => $userB->company_id,
            'freight_id'  => $freightB->id,
            'amount_due'  => '9000.00',
            'amount_paid' => '0.00',
            'status'      => 'open',
        ]);

        $this->actingAsTenant($userA)->get('/reports/vehicles')
            ->assertInertia(fn ($p) => $p
                ->where('freightsReceivableOutstanding', fn ($v) => (float) $v === 600.0)
            );
    }

    public function test_freights_receivable_outstanding_returns_zero_when_no_unpaid_receivables(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $this->actingAsTenant($user)->get('/reports/vehicles')
            ->assertInertia(fn ($p) => $p
                ->where('freightsReceivableOutstanding', fn ($v) => (float) $v === 0.0)
            );
    }

    public function test_freights_receivable_outstanding_includes_overdue_receivables(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        Receivable::factory()->create([
            'company_id'  => $user->company_id,
            'freight_id'  => $freight->id,
            'amount_due'  => '750.00',
            'amount_paid' => '0.00',
            'status'      => 'overdue',
        ]);

        $this->actingAsTenant($user)->get('/reports/vehicles')
            ->assertInertia(fn ($p) => $p
                ->where('freightsReceivableOutstanding', fn ($v) => (float) $v === 750.0)
            );
    }
}
