<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\TenantProbe;
use Tests\TestCase;

class BelongsToCompanyTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_hides_other_companies_rows(): void
    {
        [$coA, $coB] = $this->makeTwoCompanies();
        $userA = User::factory()->create(['company_id' => $coA->id]);

        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coA->id, 'label' => 'A1']);
        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coB->id, 'label' => 'B1']);

        $this->actingAs($userA);
        $visible = TenantProbe::pluck('label')->all();
        $this->assertSame(['A1'], $visible);
    }

    public function test_creating_hook_auto_fills_company_id(): void
    {
        [$coA] = $this->makeTwoCompanies();
        $userA = User::factory()->create(['company_id' => $coA->id]);
        $this->actingAs($userA);

        $probe = TenantProbe::create(['label' => 'auto']);
        $this->assertSame($coA->id, $probe->company_id);
    }

    private function makeTwoCompanies(): array
    {
        return [
            Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']),
            Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']),
        ];
    }
}
