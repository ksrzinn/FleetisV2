<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FuelRecordControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_financial_can_access_fuel_records_index(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->get('/fuel-records');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Finance/FuelRecords/Index'));
    }

    public function test_operator_cannot_access_fuel_records_index(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->get('/fuel-records');

        $response->assertForbidden();
    }

    public function test_index_tenant_isolation(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        FuelRecord::factory()->create(['company_id' => $userA->company_id]);
        FuelRecord::factory()->create(['company_id' => $userA->company_id]);
        FuelRecord::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->get('/fuel-records');

        $response->assertInertia(fn ($page) => $page->has('fuelRecords.data', 2));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_financial_can_create_fuel_record(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post('/fuel-records', [
            'vehicle_id'      => $vehicle->id,
            'liters'          => '50.000',
            'price_per_liter' => '5.5000',
            'fueled_at'       => '2026-04-20',
        ]);

        $response->assertRedirect(route('fuel-records.index'));
        $this->assertDatabaseHas('fuel_records', [
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'liters'     => '50.000',
            'total_cost' => '275.00',
        ]);
    }

    public function test_total_cost_is_computed_from_liters_times_price_per_liter(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post('/fuel-records', [
            'vehicle_id'      => $vehicle->id,
            'liters'          => '33.750',
            'price_per_liter' => '6.1230',
            'fueled_at'       => '2026-04-20',
        ]);

        // 33.750 * 6.1230 = 206.65 (bcmul)
        $this->assertDatabaseHas('fuel_records', [
            'total_cost' => '206.65',
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_financial_can_update_fuel_record(): void
    {
        $user       = $this->makeUserWithRole('Financial');
        $vehicle    = Vehicle::factory()->create(['company_id' => $user->company_id]);
        $fuelRecord = FuelRecord::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->put("/fuel-records/{$fuelRecord->id}", [
            'vehicle_id'      => $vehicle->id,
            'liters'          => '60.000',
            'price_per_liter' => '5.0000',
            'fueled_at'       => '2026-04-21',
        ]);

        $response->assertRedirect(route('fuel-records.index'));
        $this->assertDatabaseHas('fuel_records', [
            'id'         => $fuelRecord->id,
            'liters'     => '60.000',
            'total_cost' => '300.00',
        ]);
    }

    public function test_cannot_update_other_company_fuel_record(): void
    {
        $userA      = $this->makeUserWithRole('Financial');
        $userB      = $this->makeUserWithRole('Financial');
        $fuelRecord = FuelRecord::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->put("/fuel-records/{$fuelRecord->id}", [
            'vehicle_id'      => $fuelRecord->vehicle_id,
            'liters'          => '10.000',
            'price_per_liter' => '5.0000',
            'fueled_at'       => '2026-04-21',
        ]);

        $response->assertNotFound();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_financial_can_delete_fuel_record(): void
    {
        $user       = $this->makeUserWithRole('Financial');
        $fuelRecord = FuelRecord::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->delete("/fuel-records/{$fuelRecord->id}");

        $response->assertRedirect(route('fuel-records.index'));
        $this->assertDatabaseMissing('fuel_records', ['id' => $fuelRecord->id]);
    }
}
