<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE freights ENABLE ROW LEVEL SECURITY;
            ALTER TABLE freights FORCE ROW LEVEL SECURITY;

            CREATE POLICY freights_company_isolation ON freights
                USING (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                )
                WITH CHECK (
                    current_setting('app.current_company_id', true) IS NULL
                    OR current_setting('app.current_company_id', true) = ''
                    OR company_id = current_setting('app.current_company_id', true)::bigint
                );

            ALTER TABLE freight_status_history ENABLE ROW LEVEL SECURITY;
            ALTER TABLE freight_status_history FORCE ROW LEVEL SECURITY;

            CREATE POLICY freight_history_company_isolation ON freight_status_history
                USING (freight_id IN (
                    SELECT id FROM freights
                    WHERE
                        current_setting('app.current_company_id', true) IS NULL
                        OR current_setting('app.current_company_id', true) = ''
                        OR company_id = current_setting('app.current_company_id', true)::bigint
                ));
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS freights_company_isolation ON freights;
            DROP POLICY IF EXISTS freight_history_company_isolation ON freight_status_history;
            ALTER TABLE freights DISABLE ROW LEVEL SECURITY;
            ALTER TABLE freight_status_history DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
