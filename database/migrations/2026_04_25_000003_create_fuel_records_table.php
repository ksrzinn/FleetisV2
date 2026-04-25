<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('freight_id')->nullable()->constrained('freights')->nullOnDelete();
            $table->decimal('liters', 8, 3);
            $table->decimal('price_per_liter', 8, 4);
            $table->decimal('total_cost', 12, 2);
            $table->integer('odometer_km')->nullable();
            $table->date('fueled_at');
            $table->string('station', 150)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'vehicle_id', 'fueled_at']);
            $table->index(['company_id', 'fueled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_records');
    }
};
