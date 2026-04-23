<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('trailer_id')->nullable()->constrained('vehicles');
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->enum('pricing_model', ['fixed', 'per_km']);
            $table->foreignId('fixed_rate_id')->nullable()->constrained('fixed_freight_rates');
            $table->foreignId('per_km_rate_id')->nullable()->constrained('per_km_freight_rates');
            $table->string('origin', 150)->nullable();
            $table->string('destination', 150)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('toll', 10, 2)->nullable();
            $table->decimal('fuel_price_per_liter', 8, 4)->nullable();
            $table->decimal('freight_value', 12, 2)->nullable();
            $table->string('status')->default('to_start');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'client_id']);
            $table->index(['company_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freights');
    }
};
