<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionDevelopmentAdmin extends Command
{
    protected $signature = 'assetkeeper:provision-development-admin {--password-stdin : Read the temporary password from standard input instead of a hidden prompt}';

    protected $description = 'Create or update Lily’s local ICT Administrator account without changing seeded role accounts';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local/testing environments.');

            return self::FAILURE;
        }
        $password = $this->option('password-stdin') ? rtrim((string) fgets(STDIN), "\r\n") : $this->secret('Temporary development password');
        if (Validator::make(['password' => $password], ['password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()]])->fails()) {
            $this->error('Use at least 12 characters with upper/lowercase letters, a number and a symbol.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($password) {
            $department = Department::where('name', 'ICT Department')->firstOrFail();
            $user = User::updateOrCreate(['email' => 'dalilynurnisasport@gmail.com'], [
                'name' => 'Lily', 'password' => Hash::make($password),
                'role' => 'ICT Administrator', 'department_id' => $department->id, 'is_active' => true,
            ]);
            $user->refresh();
            if (! Hash::check($password, $user->getRawOriginal('password')) || ! $user->is_active || ! $user->isAdmin() || $user->department_id !== $department->id || ! $user->requiresEmailOtp()) {
                throw new \RuntimeException('Account verification failed; changes rolled back.');
            }
            Audit::record('Administrator account configured', $user, [], $user->toArray(), $user->id);
        });
        $this->info('Account configured. Hash check, Active status, ICT Administrator role, ICT Department and Email OTP requirement all verified.');

        return self::SUCCESS;
    }
}
