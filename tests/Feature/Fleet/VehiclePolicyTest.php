<?php

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class VehiclePolicyTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_vehicle(): void
    {
        $user = $this->makeUserWithRole('Admin');
        $this->actingAsTenant($user);
        $this->assertTrue($user->can('create', Vehicle::class));
    }

    public function test_operator_can_create_vehicle(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $this->actingAsTenant($user);
        $this->assertTrue($user->can('create', Vehicle::class));
    }

    public function test_financial_cannot_create_vehicle(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);
        $this->assertFalse($user->can('create', Vehicle::class));
    }

    public function test_financial_can_view_vehicle(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $type = VehicleType::factory()->create();
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);
        $this->actingAsTenant($user);
        $this->assertTrue($user->can('view', $vehicle));
    }

    public function test_operator_cannot_view_other_company_vehicle(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['vehicle_type_id' => $type->id]);
        $this->actingAsTenant($user);
        $this->assertFalse($user->can('view', $otherVehicle));
    }
}
