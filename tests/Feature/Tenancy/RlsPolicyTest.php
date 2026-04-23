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

    protected function setUp(): void
    {
        parent::setUp();

        // Superusers bypass RLS in PostgreSQL even with FORCE ROW LEVEL SECURITY.
        // We create a limited role for the raw SELECT so RLS actually fires.
        DB::unprepared("DO \$\$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rls_test_reader') THEN
                CREATE ROLE rls_test_reader NOINHERIT NOLOGIN;
            END IF;
        END \$\$");

        DB::statement('GRANT SELECT ON tenant_probes TO rls_test_reader');
    }

    public function test_rls_blocks_raw_sql_cross_tenant_read(): void
    {
        $coA = Company::create(['name' => 'A', 'cnpj' => '11111111000111', 'timezone' => 'UTC', 'status' => 'active']);
        $coB = Company::create(['name' => 'B', 'cnpj' => '22222222000122', 'timezone' => 'UTC', 'status' => 'active']);

        DB::statement('SET LOCAL app.current_company_id = '.(int) $coA->id);
        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coA->id, 'label' => 'A-row']);

        DB::statement('SET LOCAL app.current_company_id = '.(int) $coB->id);
        TenantProbe::withoutGlobalScopes()->create(['company_id' => $coB->id, 'label' => 'B-row']);

        DB::statement('SET LOCAL app.current_company_id = '.(int) $coA->id);
        DB::statement('SET LOCAL ROLE rls_test_reader');

        $rows = DB::select('select label from tenant_probes order by label');

        DB::statement('RESET ROLE');

        $this->assertSame(['A-row'], array_column($rows, 'label'));
    }
}
