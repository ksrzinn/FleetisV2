<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on');
            $table->text('description')->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('freight_id')->nullable()->constrained('freights')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'expense_category_id']);
            $table->index(['company_id', 'incurred_on']);
            $table->index(['company_id', 'vehicle_id']);
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_vehicle_or_freight_check CHECK (NOT (vehicle_id IS NOT NULL AND freight_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
