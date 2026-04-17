<?php

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class VehicleControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_operator_can_list_vehicles(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        Vehicle::factory()->count(3)->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);

        $response = $this->actingAsTenant($user)->get('/vehicles');

        $response->assertOk()->assertInertia(
            fn ($page) => $page->component('Fleet/Vehicles/Index')->has('vehicles.data', 3)
        );
    }

    public function test_financial_can_list_vehicles(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $response = $this->actingAsTenant($user)->get('/vehicles');
        $response->assertOk();
    }

    public function test_index_does_not_leak_other_company_vehicles(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);
        Vehicle::factory()->create(['vehicle_type_id' => $type->id]); // other company

        $response = $this->actingAsTenant($user)->get('/vehicles');

        $response->assertInertia(fn ($page) => $page->has('vehicles.data', 1));
    }

    public function test_operator_can_create_vehicle(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();

        $response = $this->actingAsTenant($user)->post('/vehicles', [
            'kind'            => 'vehicle',
            'vehicle_type_id' => $type->id,
            'license_plate'   => 'ABC-1234',
            'brand'           => 'Volvo',
            'model'           => 'FH',
            'year'            => 2020,
        ]);

        $response->assertRedirect('/vehicles');
        $this->assertDatabaseHas('vehicles', [
            'company_id'    => $user->company_id,
            'license_plate' => 'ABC-1234',
        ]);
    }

    public function test_financial_cannot_create_vehicle(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $type = VehicleType::factory()->create();

        $response = $this->actingAsTenant($user)->post('/vehicles', [
            'kind'            => 'vehicle',
            'vehicle_type_id' => $type->id,
            'license_plate'   => 'ABC-9999',
            'brand'           => 'Volvo',
            'model'           => 'FH',
            'year'            => 2020,
        ]);

        $response->assertForbidden();
    }

    public function test_operator_can_update_vehicle(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);

        $response = $this->actingAsTenant($user)->put("/vehicles/{$vehicle->id}", [
            'kind'            => 'vehicle',
            'vehicle_type_id' => $type->id,
            'license_plate'   => 'XYZ-5678',
            'brand'           => 'Scania',
            'model'           => 'R450',
            'year'            => 2022,
        ]);

        $response->assertRedirect('/vehicles');
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'license_plate' => 'XYZ-5678']);
    }

    public function test_operator_cannot_update_other_company_vehicle(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['vehicle_type_id' => $type->id]);

        $response = $this->actingAsTenant($user)->put("/vehicles/{$otherVehicle->id}", [
            'kind'            => 'vehicle',
            'vehicle_type_id' => $type->id,
            'license_plate'   => 'ZZZ-0000',
            'brand'           => 'x',
            'model'           => 'x',
            'year'            => 2020,
        ]);

        $response->assertNotFound();
    }

    public function test_admin_can_delete_vehicle(): void
    {
        $user = $this->makeUserWithRole('Admin');
        $type = VehicleType::factory()->create();
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);

        $response = $this->actingAsTenant($user)->delete("/vehicles/{$vehicle->id}");

        $response->assertRedirect('/vehicles');
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }
}
