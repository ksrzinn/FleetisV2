<?php

namespace Tests\Feature\Fleet;

use App\Modules\Fleet\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class DriverPolicyTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_driver(): void
    {
        $user = $this->makeUserWithRole('Admin');
        $this->actingAsTenant($user);
        $this->assertTrue($user->can('create', Driver::class));
    }

    public function test_operator_can_create_driver(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $this->actingAsTenant($user);
        $this->assertTrue($user->can('create', Driver::class));
    }

    public function test_financial_cannot_create_driver(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $this->actingAsTenant($user);
        $this->assertFalse($user->can('create', Driver::class));
    }

    public function test_financial_can_view_driver(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $driver = Driver::factory()->create(['company_id' => $user->company_id]);
        $this->actingAsTenant($user);
        $this->assertTrue($user->can('view', $driver));
    }

    public function test_operator_cannot_view_other_company_driver(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $otherDriver = Driver::factory()->create();
        $this->actingAsTenant($user);
        $this->assertFalse($user->can('view', $otherDriver));
    }
}
