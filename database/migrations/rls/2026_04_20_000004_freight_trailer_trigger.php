<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_freight_trailer()
            RETURNS TRIGGER AS $$
            DECLARE
                needs_trailer BOOLEAN;
            BEGIN
                SELECT vt.requires_trailer INTO needs_trailer
                FROM vehicles v
                JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
                WHERE v.id = NEW.vehicle_id;

                IF needs_trailer AND NEW.trailer_id IS NULL THEN
                    RAISE EXCEPTION 'Trailer obrigatório para este tipo de veículo.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS enforce_freight_trailer ON freights;

            CREATE TRIGGER enforce_freight_trailer
            BEFORE INSERT OR UPDATE ON freights
            FOR EACH ROW EXECUTE FUNCTION check_freight_trailer();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS enforce_freight_trailer ON freights;
            DROP FUNCTION IF EXISTS check_freight_trailer();
        SQL);
    }
};
