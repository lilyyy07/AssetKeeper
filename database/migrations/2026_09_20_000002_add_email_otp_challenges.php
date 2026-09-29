<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otp_challenges', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $t->string('challenge_id', 64)->unique();
            $t->string('code_hash');
            $t->string('credential_hash', 64);
            $t->dateTime('expires_at');
            $t->dateTime('sent_at');
            $t->unsignedTinyInteger('attempts')->default(0);
        });
        // Retire old enrollment material without removing users or historical schema.
        DB::table('users')->update(['mfa_secret' => null, 'mfa_confirmed_at' => null, 'recovery_codes' => null, 'mfa_last_timestamp' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otp_challenges');
    }
};

