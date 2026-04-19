<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'clients',
        'client_freight_tables',
        'fixed_freight_rates',
        'per_km_freight_rates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            if ($this->policyExists($table)) {
                continue;
            }
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_tenant_isolation ON {$table}
                USING (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                )
                WITH CHECK (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    private function tableExists(string $t): bool
    {
        return (bool) DB::selectOne('select to_regclass(?) as r', [$t])->r;
    }

    private function policyExists(string $table): bool
    {
        $row = DB::selectOne(
            'select 1 as x from pg_policies where schemaname = current_schema() and tablename = ? and policyname = ?',
            [$table, "{$table}_tenant_isolation"]
        );

        return $row !== null;
    }
};
