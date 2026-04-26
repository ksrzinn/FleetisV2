<?php

namespace Tests\Feature\Operations;

use App\Modules\Commercial\Models\FixedFreightRate;
use App\Modules\Commercial\Models\FixedFreightRatePrice;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Operations\Events\FreightEnteredAwaitingPayment;
use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TenantTestCase;

class FreightStateTransitionTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_to_start_transitions_to_in_route(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_in_route',
        ]);

        $this->assertDatabaseHas('freights', ['id' => $freight->id, 'status' => 'in_route']);
        $this->assertNotNull($freight->fresh()->started_at);
    }

    public function test_in_route_transitions_to_finished_with_toll_and_km(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->inRoute()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_finished',
            'toll' => 150.00,
            'distance_km' => 500,
            'fuel_price_per_liter' => 6.50,
        ]);

        $this->assertDatabaseHas('freights', [
            'id' => $freight->id,
            'status' => 'finished',
            'distance_km' => 500,
        ]);
        $this->assertNotNull($freight->fresh()->finished_at);
    }

    public function test_per_km_finish_requires_distance_km(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->inRoute()->create([
            'company_id' => $user->company_id,
            'pricing_model' => 'per_km',
        ]);

        $response = $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_finished',
            'toll' => 100,
            // distance_km intentionally missing
        ]);

        $response->assertSessionHasErrors('distance_km');
    }

    public function test_finished_to_awaiting_payment_locks_freight_value_for_fixed(): void
    {
        Event::fake();

        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);
        $rate = FixedFreightRate::factory()->create(['company_id' => $user->company_id]);
        FixedFreightRatePrice::factory()->create([
            'company_id' => $user->company_id,
            'fixed_freight_rate_id' => $rate->id,
            'vehicle_type_id' => $type->id,
            'price' => 1200.00,
        ]);
        $freight = Freight::factory()->finished()->create([
            'company_id' => $user->company_id,
            'vehicle_id' => $vehicle->id,
            'pricing_model' => 'fixed',
            'fixed_rate_id' => $rate->id,
        ]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_awaiting_payment',
        ]);

        $this->assertDatabaseHas('freights', ['id' => $freight->id, 'freight_value' => 1200.00]);
        Event::assertDispatched(FreightEnteredAwaitingPayment::class);
    }

    public function test_to_awaiting_payment_blocked_when_no_price_for_vehicle_type(): void
    {
        Event::fake();

        $user = $this->makeUserWithRole('Operator');
        $type = VehicleType::factory()->create();
        $vehicle = Vehicle::factory()->create(['company_id' => $user->company_id, 'vehicle_type_id' => $type->id]);
        $rate = FixedFreightRate::factory()->create(['company_id' => $user->company_id]);
        // intentionally no FixedFreightRatePrice for this vehicle type
        $freight = Freight::factory()->finished()->create([
            'company_id'    => $user->company_id,
            'vehicle_id'    => $vehicle->id,
            'pricing_model' => 'fixed',
            'fixed_rate_id' => $rate->id,
        ]);

        $response = $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_awaiting_payment',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('freight_no_value', true);
        $this->assertDatabaseHas('freights', ['id' => $freight->id, 'status' => 'finished']);
        Event::assertNotDispatched(FreightEnteredAwaitingPayment::class);
    }

    public function test_admin_can_delete_freight(): void
    {
        $user = $this->makeUserWithRole('Admin');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->delete("/freights/{$freight->id}");

        $response->assertRedirect(route('freights.index'));
        $this->assertSoftDeleted('freights', ['id' => $freight->id]);
    }

    public function test_operator_cannot_delete_freight(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $response = $this->actingAsTenant($user)->delete("/freights/{$freight->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('freights', ['id' => $freight->id]);
    }

    public function test_status_history_is_recorded_on_transition(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post("/freights/{$freight->id}/transition", [
            'transition' => 'to_in_route',
        ]);

        $this->assertDatabaseHas('freight_status_history', [
            'freight_id' => $freight->id,
            'from_status' => 'to_start',
            'to_status' => 'in_route',
        ]);
    }
}
