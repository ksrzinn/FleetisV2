<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TenantProbe;
use Tests\TestCase;

class RlsPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rls_blocks_raw_sql_cross_tenant_read(): void
    {
        $coA = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);
        $coB = Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']);

        DB::statement('SET LOCAL app.current_company_id = '.(int) $coA->id);
        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coA->id, 'label' => 'A-row']);

        DB::statement('SET LOCAL app.current_company_id = '.(int) $coB->id);
        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coB->id, 'label' => 'B-row']);

        DB::statement('SET LOCAL app.current_company_id = '.(int) $coA->id);
        $rows = DB::select('select label from tenant_probes order by label');
        $this->assertSame(['A-row'], array_column($rows, 'label'));
    }
}
