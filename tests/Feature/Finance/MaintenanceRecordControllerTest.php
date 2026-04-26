<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class MaintenanceRecordControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_financial_can_access_maintenance_records_index(): void
    {
        $user = $this->makeUserWithRole('Financial');

        $response = $this->actingAsTenant($user)->get('/maintenance-records');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Finance/Maintenance/Index'));
    }

    public function test_operator_cannot_access_maintenance_records_index(): void
    {
        $user = $this->makeUserWithRole('Operator');

        $response = $this->actingAsTenant($user)->get('/maintenance-records');

        $response->assertForbidden();
    }

    public function test_index_tenant_isolation(): void
    {
        $userA = $this->makeUserWithRole('Financial');
        $userB = $this->makeUserWithRole('Financial');

        MaintenanceRecord::factory()->create(['company_id' => $userA->company_id]);
        MaintenanceRecord::factory()->create(['company_id' => $userA->company_id]);
        MaintenanceRecord::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->get('/maintenance-records');

        $response->assertInertia(fn ($page) => $page->has('maintenanceRecords.data', 2));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_financial_can_create_maintenance_record(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post('/maintenance-records', [
            'vehicle_id'   => $vehicle->id,
            'type'         => 'preventive',
            'description'  => 'Oil change',
            'cost'         => '350.00',
            'performed_on' => '2026-04-20',
        ]);

        $response->assertRedirect(route('maintenance-records.index'));
        $this->assertDatabaseHas('maintenance_records', [
            'company_id'  => $user->company_id,
            'vehicle_id'  => $vehicle->id,
            'type'        => 'preventive',
            'description' => 'Oil change',
        ]);
    }

    public function test_invalid_type_value_is_rejected(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->post('/maintenance-records', [
            'vehicle_id'   => $vehicle->id,
            'type'         => 'invalid_type',
            'description'  => 'Oil change',
            'cost'         => '350.00',
            'performed_on' => '2026-04-20',
        ]);

        $response->assertSessionHasErrors('type');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_financial_can_update_maintenance_record(): void
    {
        $user    = $this->makeUserWithRole('Financial');
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id]);
        $record  = MaintenanceRecord::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->put("/maintenance-records/{$record->id}", [
            'vehicle_id'   => $vehicle->id,
            'type'         => 'corrective',
            'description'  => 'Brake replacement',
            'cost'         => '1500.00',
            'performed_on' => '2026-04-21',
        ]);

        $response->assertRedirect(route('maintenance-records.index'));
        $this->assertDatabaseHas('maintenance_records', [
            'id'   => $record->id,
            'type' => 'corrective',
            'cost' => '1500.00',
        ]);
    }

    public function test_cannot_update_other_company_maintenance_record(): void
    {
        $userA  = $this->makeUserWithRole('Financial');
        $userB  = $this->makeUserWithRole('Financial');
        $record = MaintenanceRecord::factory()->create(['company_id' => $userB->company_id]);

        $response = $this->actingAsTenant($userA)->put("/maintenance-records/{$record->id}", [
            'vehicle_id'   => $record->vehicle_id,
            'type'         => 'routine',
            'description'  => 'Test',
            'cost'         => '100.00',
            'performed_on' => '2026-04-21',
        ]);

        $response->assertNotFound();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_financial_can_delete_maintenance_record(): void
    {
        $user   = $this->makeUserWithRole('Financial');
        $record = MaintenanceRecord::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->delete("/maintenance-records/{$record->id}");

        $response->assertRedirect(route('maintenance-records.index'));
        $this->assertDatabaseMissing('maintenance_records', ['id' => $record->id]);
    }
}
