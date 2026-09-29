<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:255']);
        $users = User::with('department')->when($r->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->search.'%')->orWhere('email', 'like', '%'.$r->search.'%')))->orderBy('name')->paginate(15)->withQueryString();
        $departments = Department::orderBy('name')->get();

        return view('users', compact('users', 'departments'));
    }

    public function save(Request $r, ?User $user = null)
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)], 'password' => [$user ? 'nullable' : 'required', Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'], 'role' => ['required', Rule::in(User::ROLES)], 'department_id' => ['nullable', Department::selectionRule($user?->department_id)], 'is_active' => 'required|boolean']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        DB::transaction(function () use ($r, $data, $user) {
            $admins = User::where('role', User::ROLES[0])->where('is_active', true)->lockForUpdate()->get();
            if ($user) {
                $user = User::lockForUpdate()->findOrFail($user->id);
                if ($user->id === $r->user()->id && (! $data['is_active'] || $data['role'] !== User::ROLES[0])) {
                    throw ValidationException::withMessages(['role' => 'You cannot deactivate or demote your own administrator account.']);
                }if ($user->isAdmin() && $user->is_active && $admins->count() <= 1 && (! $data['is_active'] || $data['role'] !== User::ROLES[0])) {
                    throw ValidationException::withMessages(['role' => 'Keep at least one active administrator.']);
                }$old = $user->toArray();
                $user->update($data);
                Audit::record('User updated', $user, $old, $user->toArray());
                if ($old['role'] !== $user->role) {
                    Audit::record('User role changed', $user, $old, $user->toArray());
                }if ((bool) $old['is_active'] !== $user->is_active) {
                    Audit::record($user->is_active ? 'Account activated' : 'Account deactivated', $user, $old, $user->toArray());
                }
            } else {
                $user = User::create($data);
                Audit::record('User created', $user, [], $user->toArray());
            }
        });

        return back()->with('success', 'User saved.');
    }

    public function profile()
    {
        return view('profile');
    }

    public function updateProfile(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($r->user())]]);
        $r->user()->update($data);
        Audit::record('Profile updated', $r->user());

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $r->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $r->user()->id)->where('id', '!=', $r->session()->getId())->delete();
        $r->session()->regenerate();
        Audit::record('Password changed', $r->user());

        return back()->with('success', 'Password changed. Other sessions have been revoked.');
    }
}
