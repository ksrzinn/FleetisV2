<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TenantProbe;
use Tests\TenantTestCase;

class TenantTestCaseTest extends TenantTestCase
{
    use RefreshDatabase;

    public function test_acting_as_tenant_sets_session_variable_and_authenticates_user(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAsTenant($user);

        $current = DB::selectOne("select current_setting('app.current_company_id', true) as v")->v;
        $this->assertSame((string) $company->id, $current);
        $this->assertTrue(auth()->check());
        $this->assertSame($user->id, auth()->id());
    }

    public function test_factories_created_under_tenant_context_are_scoped(): void
    {
        $coA = Company::factory()->create();
        $coB = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $coA->id]);

        DB::statement("SET LOCAL app.current_company_id = ''");
        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coA->id, 'label' => 'a']);
        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coB->id, 'label' => 'b']);

        $this->actingAsTenant($userA);

        $this->assertSame(['a'], TenantProbe::pluck('label')->all());
    }
}
