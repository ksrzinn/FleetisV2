<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // company_id is nullable in Epic 1 only so the stock users migration
        // doesn't break. Epic 2's first migration flips it to nullable(false)
        // once Task 11 signup flow always sets it.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('users_email_unique');
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'email']);
            $table->unique('email');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
