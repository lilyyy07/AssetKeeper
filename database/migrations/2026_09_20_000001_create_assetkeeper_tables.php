<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('Department Staff')->index();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('is_active')->default(true);
            $t->text('mfa_secret')->nullable();
            $t->timestamp('mfa_confirmed_at')->nullable();
            $t->text('recovery_codes')->nullable();
            $t->unsignedBigInteger('mfa_last_timestamp')->nullable();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('asset_tag')->unique();
            $t->string('name');
            $t->string('category')->index();
            $t->string('brand')->nullable();
            $t->string('model')->nullable();
            $t->string('serial_number')->nullable()->unique();
            $t->date('purchase_date')->nullable();
            $t->decimal('purchase_price', 12, 2)->nullable();
            $t->date('warranty_expiry')->nullable();
            $t->string('supplier')->nullable();
            $t->string('location')->index();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status')->default('Available')->index();
            $t->string('condition')->default('Good');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->string('assigned_to');
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->string('location');
            $t->date('allocation_date');
            $t->date('expected_return_date')->nullable();
            $t->date('actual_return_date')->nullable();
            $t->string('status')->default('Active')->index();
            $t->text('remarks')->nullable();
            $t->string('return_condition')->nullable();
            $t->text('return_remarks')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['asset_id', 'status']);
        });
        Schema::create('maintenance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->text('issue');
            $t->string('type');
            $t->string('priority');
            $t->date('date_reported');
            $t->foreignId('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status')->default('Pending')->index();
            $t->date('estimated_completion_date')->nullable();
            $t->date('actual_completion_date')->nullable();
            $t->text('remarks')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['asset_id', 'status']);
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('action')->index();
            $t->string('entity_type')->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('description');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'maintenance_records', 'allocations', 'assets'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('department_id');
            $t->dropColumn(['role', 'is_active', 'mfa_secret', 'mfa_confirmed_at', 'recovery_codes', 'mfa_last_timestamp']);
        });
        Schema::dropIfExists('departments');
    }
};
