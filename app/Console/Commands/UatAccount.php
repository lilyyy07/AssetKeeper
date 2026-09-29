<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UatAccount extends Command
{
    protected $signature = 'assetkeeper:uat-account {email : Dedicated UAT account email} {--role= : One of the existing three roles} {--name= : Display name} {--department= : Active department ID} {--deactivate : Deactivate this UAT account without deleting history}';

    protected $description = 'Provision/reset or deactivate a dedicated UAT account using hidden password prompts';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $this->error('Supply a valid email address.');

            return self::FAILURE;
        }
        $existing = User::where('email', $email)->first();
        if ($existing && ! $existing->is_uat_account) {
            $this->error('Refusing to convert or change an existing non-UAT account. Use a dedicated email.');

            return self::FAILURE;
        }
        if ($this->option('deactivate')) {
            if (! $existing) {
                $this->error('No marked UAT account found.');

                return self::FAILURE;
            }
            if ($existing->isAdmin() && $existing->is_active && ! User::where('role', User::ROLES[0])->where('is_active', true)->where('id', '!=', $existing->id)->exists()) {
                $this->error('Keep another active administrator before deactivation.');

                return self::FAILURE;
            }
            DB::transaction(function () use ($existing) {
                $old = $existing->toArray();
                $existing->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $existing->id)->delete();
                Audit::record('UAT account deactivated', $existing, $old, $existing->toArray(), $existing->id);
            });
            $this->info('UAT account deactivated; historical records retained.');

            return self::SUCCESS;
        }
        $data = ['email' => $email, 'role' => $this->option('role') ?? $existing?->role, 'name' => $this->option('name') ?? $existing?->name, 'department_id' => $this->option('department') ?? $existing?->department_id];
        $validator = Validator::make($data, ['email' => 'required|email|max:255', 'name' => 'required|string|max:255', 'role' => ['required', Rule::in(User::ROLES)], 'department_id' => ['nullable', Department::selectionRule($existing?->department_id)]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        if ($existing?->isAdmin() && $existing->is_active && $data['role'] !== User::ROLES[0] && ! User::where('role', User::ROLES[0])->where('is_active', true)->where('id', '!=', $existing->id)->exists()) {
            $this->error('Keep another active administrator before changing this role.');

            return self::FAILURE;
        }
        // Do not accept a password argument, option, default, or echoed fallback.
        if (! $this->input->isInteractive()) {
            $this->error('Use an interactive terminal for hidden password entry.');

            return self::FAILURE;
        }
        $password = $this->secret('New UAT password', false);
        $confirmation = $this->secret('Confirm UAT password', false);
        if (Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]])->fails()) {
            $this->error('Passwords must match and contain at least 12 characters, upper/lowercase, a number and a symbol.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($existing, $data, $password) {
            $user = $existing ?? new User;
            $old = $existing?->toArray() ?? [];
            $user->fill($data)->forceFill(['password' => $password, 'is_active' => true, 'is_uat_account' => true, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record('UAT account configured', $user, $old, $user->toArray(), $user->id);
        });
        $this->info('Dedicated UAT account saved. OTP exception requires server UAT mode. No credentials are printed.');

        return self::SUCCESS;
    }
}
