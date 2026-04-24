<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('payment_term_type', ['monthly', 'weekly', 'daily', 'days_after'])
                ->nullable()
                ->after('active');
            $table->unsignedSmallInteger('payment_term_value')
                ->nullable()
                ->after('payment_term_type');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['payment_term_type', 'payment_term_value']);
        });
    }
};
