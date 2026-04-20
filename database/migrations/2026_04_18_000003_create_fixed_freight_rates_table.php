<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_freight_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_freight_table_id')
                ->constrained('client_freight_tables')
                ->cascadeOnDelete();
            $table->string('name');
            $table->decimal('avg_km', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['client_freight_table_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_freight_rates');
    }
};
