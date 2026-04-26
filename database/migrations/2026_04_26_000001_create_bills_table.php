<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('supplier');
            $table->text('description')->nullable();
            $table->enum('bill_type', ['one_time', 'recurring', 'installment']);
            $table->decimal('total_amount', 12, 2);
            $table->date('due_date');
            $table->enum('recurrence_cadence', ['weekly', 'biweekly', 'monthly', 'yearly'])->nullable();
            $table->tinyInteger('recurrence_day')->nullable();
            $table->date('recurrence_end')->nullable();
            $table->unsignedSmallInteger('installment_count')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'bill_type']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
