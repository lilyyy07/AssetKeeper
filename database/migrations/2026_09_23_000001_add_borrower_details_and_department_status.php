<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });
        Schema::table('allocations', function (Blueprint $table) {
            $table->string('borrower_type', 40)->nullable();
            $table->string('borrower_identifier', 64)->nullable();
            $table->string('borrower_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('allocations', fn (Blueprint $table) => $table->dropColumn(['borrower_type', 'borrower_identifier', 'borrower_email']));
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
