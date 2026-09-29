<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_uat_account')->default(false));
    }

    public function down(): void
    {
        // Preserve account attribution; rollback must not silently erase UAT identity.
        throw new RuntimeException('Disable UAT mode and deactivate accounts instead of dropping UAT attribution.');
    }
};
