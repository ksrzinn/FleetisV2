<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->enum('kind', ['vehicle', 'trailer']);
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types');
            $table->string('license_plate');
            $table->string('renavam')->nullable();
            $table->string('brand');
            $table->string('model');
            $table->smallInteger('year');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'license_plate']);
            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
