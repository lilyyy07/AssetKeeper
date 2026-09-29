<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        $names = array_unique(array_merge(['Laptop', 'Desktop', 'Printer', 'Network Device', 'Projector', 'Monitor', 'Server', 'Peripheral'], DB::table('assets')->distinct()->pluck('category')->all()));
        foreach ($names as $name) {
            DB::table('asset_categories')->insertOrIgnore(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('maintenance_records', function (Blueprint $t) {
            $t->dateTime('date_reported')->change();
            $t->dateTime('actual_completion_date')->nullable()->change();
            $t->dateTime('problem_attended_at')->nullable();
            $t->dateTime('repair_started_at')->nullable();
            $t->boolean('reported_time_recorded')->default(false);
            $t->boolean('completion_time_recorded')->default(false);
        });
        // Retain identity, role text, and all historical references.
        DB::table('users')->where('role', 'Department Staff')->update(['is_active' => false]);
        // Keep the existing users table intact: SQLite rebuilds can break historical foreign keys.
    }

    public function down(): void
    {
        throw new RuntimeException('This data-preserving migration is forward-only.');
    }
};
