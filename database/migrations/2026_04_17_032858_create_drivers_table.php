<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('cpf', 14);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'cpf']);
            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
