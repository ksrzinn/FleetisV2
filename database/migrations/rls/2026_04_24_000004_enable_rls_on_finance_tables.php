<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE receivables ENABLE ROW LEVEL SECURITY;
            ALTER TABLE receivables FORCE ROW LEVEL SECURITY;

            CREATE POLICY receivables_company_isolation ON receivables
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

            ALTER TABLE payments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE payments FORCE ROW LEVEL SECURITY;

            CREATE POLICY payments_company_isolation ON payments
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
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS receivables_company_isolation ON receivables;
            DROP POLICY IF EXISTS payments_company_isolation ON payments;
            ALTER TABLE receivables DISABLE ROW LEVEL SECURITY;
            ALTER TABLE payments DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
