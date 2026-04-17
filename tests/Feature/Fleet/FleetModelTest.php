<?php

namespace Tests\Feature\Fleet;

use App\Models\User;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_belongs_to_company_scope(): void
    {
        $coA = Company::factory()->create();
        $coB = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $coA->id]);

        $type = VehicleType::factory()->create();
        Vehicle::factory()->create(['company_id' => $coA->id, 'vehicle_type_id' => $type->id]);
        Vehicle::factory()->create(['company_id' => $coB->id, 'vehicle_type_id' => $type->id]);

        $this->actingAs($userA);

        $this->assertSame(1, Vehicle::count());
    }

    public function test_driver_belongs_to_company_scope(): void
    {
        $coA = Company::factory()->create();
        $coB = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $coA->id]);

        Driver::factory()->create(['company_id' => $coA->id]);
        Driver::factory()->create(['company_id' => $coB->id]);

        $this->actingAs($userA);

        $this->assertSame(1, Driver::count());
    }
}
