<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE expense_categories ENABLE ROW LEVEL SECURITY;
            ALTER TABLE expense_categories FORCE ROW LEVEL SECURITY;

            CREATE POLICY expense_categories_company_isolation ON expense_categories
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

            ALTER TABLE expenses ENABLE ROW LEVEL SECURITY;
            ALTER TABLE expenses FORCE ROW LEVEL SECURITY;

            CREATE POLICY expenses_company_isolation ON expenses
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

            ALTER TABLE fuel_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE fuel_records FORCE ROW LEVEL SECURITY;

            CREATE POLICY fuel_records_company_isolation ON fuel_records
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

            ALTER TABLE maintenance_records ENABLE ROW LEVEL SECURITY;
            ALTER TABLE maintenance_records FORCE ROW LEVEL SECURITY;

            CREATE POLICY maintenance_records_company_isolation ON maintenance_records
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
            DROP POLICY IF EXISTS expense_categories_company_isolation ON expense_categories;
            DROP POLICY IF EXISTS expenses_company_isolation ON expenses;
            DROP POLICY IF EXISTS fuel_records_company_isolation ON fuel_records;
            DROP POLICY IF EXISTS maintenance_records_company_isolation ON maintenance_records;
            ALTER TABLE expense_categories DISABLE ROW LEVEL SECURITY;
            ALTER TABLE expenses DISABLE ROW LEVEL SECURITY;
            ALTER TABLE fuel_records DISABLE ROW LEVEL SECURITY;
            ALTER TABLE maintenance_records DISABLE ROW LEVEL SECURITY;
        SQL);
    }
};
