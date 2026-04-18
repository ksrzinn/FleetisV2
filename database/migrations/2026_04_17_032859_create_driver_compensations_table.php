<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('driver_id')->constrained('drivers');
            $table->string('type'); // percentage | fixed_per_freight | monthly_salary
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'driver_id']);
        });

        // One active (effective_to IS NULL) per driver per type
        DB::statement('
            CREATE UNIQUE INDEX driver_compensations_unique_active_type
                ON driver_compensations (driver_id, type)
                WHERE effective_to IS NULL
        ');

        // Ensure exactly one value column is non-null, matching type
        DB::statement("
            ALTER TABLE driver_compensations
                ADD CONSTRAINT chk_compensation_value CHECK (
                    (type = 'percentage'       AND percentage     IS NOT NULL AND fixed_amount IS NULL AND monthly_salary IS NULL) OR
                    (type = 'fixed_per_freight' AND fixed_amount   IS NOT NULL AND percentage  IS NULL AND monthly_salary IS NULL) OR
                    (type = 'monthly_salary'    AND monthly_salary IS NOT NULL AND percentage  IS NULL AND fixed_amount   IS NULL)
                )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_compensations');
    }
};
