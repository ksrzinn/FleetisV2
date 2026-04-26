<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['bill_id', 'due_date']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_installments');
    }
};
