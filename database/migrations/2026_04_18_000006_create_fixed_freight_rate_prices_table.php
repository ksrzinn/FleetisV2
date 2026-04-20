<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_freight_rate_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('fixed_freight_rate_id')
                ->constrained('fixed_freight_rates')
                ->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types');
            $table->decimal('price', 12, 2);
            $table->decimal('tolls', 12, 2)->nullable();
            $table->decimal('fuel_cost', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['fixed_freight_rate_id', 'vehicle_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_freight_rate_prices');
    }
};
