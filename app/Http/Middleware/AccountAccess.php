<?php

namespace App\Http\Middleware;

use App\Services\EmailOtp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! in_array($user->role, \App\Models\User::ROLES, true) || ! $user->is_active || ($request->session()->has('last_activity') && time() - $request->session()->get('last_activity') > 1800)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your session ended. Please sign in again.']);
        }
        $request->session()->put('last_activity', time());
        if ($user->requiresEmailOtp() && $request->session()->get(EmailOtp::SESSION_KEY) !== $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Please sign in again to verify your identity by email.']);
        }

        return $next($request);
    }
}
