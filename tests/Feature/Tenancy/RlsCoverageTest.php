<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RlsCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_table_with_company_id_has_rls_policy(): void
    {
        $tenantTables = collect(DB::select(<<<'SQL'
            select table_name
            from information_schema.columns
            where table_schema = 'public'
              and column_name = 'company_id'
        SQL))->pluck('table_name')->sort()->values()->all();

        $this->assertNotEmpty($tenantTables, 'Expected at least one tenant-scoped table.');

        $tablesWithRls = collect(DB::select(<<<'SQL'
            select c.relname as table_name
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public'
              and c.relrowsecurity = true
              and c.relforcerowsecurity = true
        SQL))->pluck('table_name')->all();

        $tablesWithPolicy = collect(DB::select(<<<'SQL'
            select distinct tablename
            from pg_policies
            where schemaname = 'public'
        SQL))->pluck('tablename')->all();

        foreach ($tenantTables as $table) {
            $this->assertContains(
                $table,
                $tablesWithRls,
                "Table [{$table}] has company_id but is missing FORCE ROW LEVEL SECURITY."
            );
            $this->assertContains(
                $table,
                $tablesWithPolicy,
                "Table [{$table}] has company_id but is missing a tenant-isolation policy."
            );
        }
    }
}
