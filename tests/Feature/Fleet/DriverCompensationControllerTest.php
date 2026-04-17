<?php

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Models\Driver;
use App\Modules\Fleet\Models\DriverCompensation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class DriverCompensationControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_operator_can_view_compensations_page(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $response = $this->actingAsTenant($user)->get("/drivers/{$driver->id}/compensations");
        $response->assertOk()->assertInertia(fn ($page) => $page->component('Fleet/Drivers/Compensations/Index'));
    }

    public function test_operator_can_create_percentage_compensation(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $response = $this->actingAsTenant($user)->post("/drivers/{$driver->id}/compensations", [
            'type' => 'percentage', 'percentage' => 10.5, 'effective_from' => '2026-01-01',
        ]);
        $response->assertRedirect("/drivers/{$driver->id}/compensations");
        $this->assertDatabaseHas('driver_compensations', [
            'driver_id' => $driver->id, 'type' => 'percentage', 'percentage' => 10.5, 'effective_to' => null,
        ]);
    }

    public function test_creating_same_type_closes_previous(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);

        DriverCompensation::factory()->create([
            'company_id' => $user->company_id, 'driver_id' => $driver->id,
            'type' => 'percentage', 'percentage' => 10.00, 'effective_from' => '2025-01-01',
        ]);

        $this->actingAsTenant($user)->post("/drivers/{$driver->id}/compensations", [
            'type' => 'percentage', 'percentage' => 15.00, 'effective_from' => '2026-01-01',
        ]);

        $this->assertDatabaseHas('driver_compensations', ['driver_id' => $driver->id, 'percentage' => 10.00, 'effective_to' => now()->toDateString()]);
        $this->assertDatabaseHas('driver_compensations', ['driver_id' => $driver->id, 'percentage' => 15.00, 'effective_to' => null]);
    }

    public function test_driver_can_have_two_active_types_simultaneously(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);

        $this->actingAsTenant($user)->post("/drivers/{$driver->id}/compensations", [
            'type' => 'percentage', 'percentage' => 10.00, 'effective_from' => '2026-01-01',
        ]);
        $this->actingAsTenant($user)->post("/drivers/{$driver->id}/compensations", [
            'type' => 'monthly_salary', 'monthly_salary' => 3000.00, 'effective_from' => '2026-01-01',
        ]);

        $this->assertSame(2, $driver->activeCompensations()->count());
    }

    public function test_financial_cannot_create_compensation(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $response = $this->actingAsTenant($user)->post("/drivers/{$driver->id}/compensations", [
            'type' => 'percentage', 'percentage' => 10.00, 'effective_from' => '2026-01-01',
        ]);
        $response->assertForbidden();
    }

    public function test_operator_cannot_create_compensation_for_other_company_driver(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $otherDriver = Driver::factory()->create(); // different company

        $response = $this->actingAsTenant($user)->post("/drivers/{$otherDriver->id}/compensations", [
            'type' => 'percentage',
            'percentage' => 10.00,
            'effective_from' => now()->toDateString(),
        ]);

        $response->assertNotFound();
    }

    public function test_compensation_page_does_not_leak_other_company_driver(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $otherDriver = Driver::factory()->create();
        $response = $this->actingAsTenant($user)->get("/drivers/{$otherDriver->id}/compensations");
        $response->assertNotFound();
    }
}
