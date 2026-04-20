<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_freight_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('pricing_model');   // 'fixed' | 'per_km'
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'client_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_freight_tables');
    }
};
