<?php

namespace Tests\Feature\Operations;

use App\Modules\Operations\Models\Freight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

class FreightPolicyTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_financial_cannot_create_freight(): void
    {
        $user = $this->makeUserWithRole('Financial');
        $response = $this->actingAsTenant($user)->get('/freights/create');
        $response->assertForbidden();
    }

    public function test_operator_can_view_freight_index(): void
    {
        $user = $this->makeUserWithRole('Operator');
        $response = $this->actingAsTenant($user)->get('/freights');
        $response->assertOk();
    }

    public function test_operator_cannot_view_other_company_freight(): void
    {
        $userA = $this->makeUserWithRole('Operator');
        $freight = Freight::factory()->create(); // different company

        $response = $this->actingAsTenant($userA)->get("/freights/{$freight->id}");
        $response->assertNotFound();
    }
}
