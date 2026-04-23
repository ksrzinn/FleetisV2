<?php

namespace Tests\Feature\Operations;

use App\Modules\Commercial\Models\Client;
use App\Modules\Commercial\Models\ClientFreightTable;
use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FreightControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_fixed_freight(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create(['requires_trailer' => false]);
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id, 'kind' => 'vehicle']);
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $client = Client::factory()->create(['company_id' => $user->company_id]);
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id, 'client_id' => $client->id, 'pricing_model' => 'fixed']);
        $rate = FixedFreightRate::factory()->create(['company_id' => $user->company_id, 'client_freight_table_id' => $table->id]);

        $response = $this->actingAsTenant($user)->post('/freights', [
            'client_id' => $client->id,
            'pricing_model' => 'fixed',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'fixed_rate_id' => $rate->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('freights', [
            'company_id' => $user->company_id,
            'client_id' => $client->id,
            'status' => 'to_start',
        ]);
    }

    public function test_creating_freight_requires_trailer_when_vehicle_type_demands_it(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create(['requires_trailer' => true]);
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id, 'kind' => 'vehicle']);
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $client = Client::factory()->create(['company_id' => $user->company_id]);
        $table = ClientFreightTable::factory()->create(['company_id' => $user->company_id, 'client_id' => $client->id, 'pricing_model' => 'fixed']);
        $rate = FixedFreightRate::factory()->create(['company_id' => $user->company_id, 'client_freight_table_id' => $table->id]);

        $response = $this->actingAsTenant($user)->post('/freights', [
            'client_id' => $client->id,
            'pricing_model' => 'fixed',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'fixed_rate_id' => $rate->id,
            // trailer_id intentionally omitted
        ]);

        $response->assertSessionHasErrors('trailer_id');
    }

    public function test_index_does_not_leak_other_company_freights(): void
    {
        $userA = $this->makeUserWithRole('Operator');
        Freight::factory()->create(['company_id' => $userA->company_id]);
        Freight::factory()->create(); // other company

        $response = $this->actingAsTenant($userA)->get('/freights');
        $response->assertInertia(fn ($page) => $page->has('freights.data', 1));
    }

    public function test_db_trigger_blocks_freight_without_trailer_when_required(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create(['requires_trailer' => true]);
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id, 'kind' => 'vehicle']);
        $client = Client::factory()->create(['company_id' => $user->company_id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('freights')->insert([
            'company_id'    => $user->company_id,
            'client_id'     => $client->id,
            'vehicle_id'    => $vehicle->id,
            'trailer_id'    => null,
            'pricing_model' => 'fixed',
            'status'        => 'to_start',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
