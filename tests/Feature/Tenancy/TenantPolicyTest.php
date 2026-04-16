<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Policies\TenantPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\TenantProbe;
use Tests\TestCase;

class TenantPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_company_passes_tenant_check(): void
    {
        $company = Company::create([
            'name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $probe = TenantProbe::withoutGlobalScopes()->create([
            'label' => 'x', 'company_id' => $company->id,
        ]);

        $policy = new class extends TenantPolicy {};
        $this->assertTrue($policy->belongsToTenant($user, $probe));
    }

    public function test_different_company_fails_tenant_check(): void
    {
        $coA = Company::create([
            'name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active',
        ]);
        $coB = Company::create([
            'name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active',
        ]);
        $user = User::factory()->create(['company_id' => $coA->id]);
        $probe = TenantProbe::withoutGlobalScopes()->create([
            'label' => 'x', 'company_id' => $coB->id,
        ]);

        $policy = new class extends TenantPolicy {};
        $this->assertFalse($policy->belongsToTenant($user, $probe));
    }
}
