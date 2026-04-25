<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE bills ENABLE ROW LEVEL SECURITY;
            ALTER TABLE bills FORCE ROW LEVEL SECURITY;

            CREATE POLICY bills_company_isolation ON bills
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

            ALTER TABLE bill_installments ENABLE ROW LEVEL SECURITY;
            ALTER TABLE bill_installments FORCE ROW LEVEL SECURITY;

            CREATE POLICY bill_installments_company_isolation ON bill_installments
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
            DROP POLICY IF EXISTS bills_company_isolation ON bills;
            DROP POLICY IF EXISTS bill_installments_company_isolation ON bill_installments;
            ALTER TABLE bills DISABLE ROW LEVEL SECURITY;
            ALTER TABLE bill_installments DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
