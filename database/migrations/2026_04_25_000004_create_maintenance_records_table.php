<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->enum('type', ['preventive', 'corrective', 'emergency', 'routine']);
            $table->text('description');
            $table->decimal('cost', 12, 2);
            $table->integer('odometer_km')->nullable();
            $table->date('performed_on');
            $table->string('provider', 150)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'vehicle_id', 'performed_on']);
            $table->index(['company_id', 'performed_on']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
