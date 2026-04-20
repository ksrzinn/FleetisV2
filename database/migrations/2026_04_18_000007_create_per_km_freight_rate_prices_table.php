<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_km_freight_rate_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('per_km_freight_rate_id')
                ->constrained('per_km_freight_rates')
                ->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types');
            $table->decimal('rate_per_km', 10, 4);
            $table->timestamps();
            $table->unique(['per_km_freight_rate_id', 'vehicle_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_km_freight_rate_prices');
    }
};
