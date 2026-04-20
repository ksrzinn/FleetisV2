<?php

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class DriverControllerTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_operator_can_list_drivers(): void
    {
        $user = $this->makeUserWithRole('Operator');
        Driver::factory()->count(2)->create(['company_id' => $user->company_id]);
        $response = $this->actingAsTenant($user)->get('/drivers');
        $response->assertOk()->assertInertia(
            fn ($page) => $page->component('Fleet/Drivers/Index')->has('drivers.data', 2)
        );
    }

    public function test_index_does_not_leak_other_company_drivers(): void
    {
        $user = $this->makeUserWithRole('Operator');
        Driver::factory()->create(['company_id' => $user->company_id]);
        Driver::factory()->create();
        $response = $this->actingAsTenant($user)->get('/drivers');
        $response->assertInertia(fn ($page) => $page->has('drivers.data', 1));
    }

    public function test_operator_can_create_driver(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $response = $this->actingAsTenant($user)->post('/drivers', [
            'name' => 'João Silva', 'cpf' => '529.982.247-25',
            'phone' => '(11) 99999-0000', 'birth_date' => '1985-06-15',
        ]);
        $response->assertRedirect('/drivers');
        $this->assertDatabaseHas('drivers', ['company_id' => $user->company_id, 'cpf' => '529.982.247-25']);
    }

    public function test_invalid_cpf_is_rejected(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $response = $this->actingAsTenant($user)->post('/drivers', ['name' => 'Fulano', 'cpf' => '111.111.111-11']);
        $response->assertSessionHasErrors('cpf');
    }

    public function test_duplicate_cpf_within_company_is_rejected(): void
    {
        $user = $this->makeUserWithRole('Operator');
        Driver::factory()->create(['company_id' => $user->company_id, 'cpf' => '529.982.247-25']);
        $response = $this->actingAsTenant($user)->post('/drivers', ['name' => 'Outro', 'cpf' => '529.982.247-25']);
        $response->assertSessionHasErrors('cpf');
    }

    public function test_same_cpf_in_different_company_is_allowed(): void
    {
        $user = $this->makeUserWithRole('Operator');
        Driver::factory()->create(['cpf' => '529.982.247-25']);
        $response = $this->actingAsTenant($user)->post('/drivers', ['name' => 'João', 'cpf' => '529.982.247-25']);
        $response->assertRedirect('/drivers');
    }

    public function test_financial_cannot_create_driver(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $response = $this->actingAsTenant($user)->post('/drivers', ['name' => 'x', 'cpf' => '529.982.247-25']);
        $response->assertForbidden();
    }

    public function test_operator_can_update_driver(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $response = $this->actingAsTenant($user)->put("/drivers/{$driver->id}", ['name' => 'Maria Santos', 'cpf' => '529.982.247-25']);
        $response->assertRedirect('/drivers');
        $this->assertDatabaseHas('drivers', ['id' => $driver->id, 'name' => 'Maria Santos']);
    }

    public function test_operator_cannot_update_other_company_driver(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $otherDriver = Driver::factory()->create(); // different company

        $response = $this->actingAsTenant($user)->put("/drivers/{$otherDriver->id}", [
            'name' => 'Hack',
            'cpf' => '529.982.247-25',
        ]);

        $response->assertNotFound();
    }

    public function test_admin_can_soft_delete_driver(): void
    {
        $user = $this->makeUserWithRole('Admin');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $response = $this->actingAsTenant($user)->delete("/drivers/{$driver->id}");
        $response->assertRedirect('/drivers');
        $this->assertSoftDeleted('drivers', ['id' => $driver->id]);
    }
}
