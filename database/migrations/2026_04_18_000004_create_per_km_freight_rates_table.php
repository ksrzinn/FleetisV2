<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('per_km_freight_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->char('state', 2);
            $table->timestamps();
            $table->unique(['company_id', 'client_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('per_km_freight_rates');
    }
};
