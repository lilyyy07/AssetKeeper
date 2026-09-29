@extends('auth.layout')
@section('title', 'Sign in')
@section('content')
<div class="auth-security-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></div>
<p class="auth-eyebrow">YOUR ICT WORKSPACE</p>
<h2>Welcome Back</h2><p class="auth-intro">Sign in to continue to AssetKeeper</p>
<form method="post" action="{{ route('login.submit') }}">
    @csrf
    <div class="mb-4"><label class="form-label" for="email">Email Address</label><div class="auth-input"><i class="bi bi-envelope" aria-hidden="true"></i><input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="you@example.edu.my" required autofocus></div></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label><div class="auth-input"><i class="bi bi-lock" aria-hidden="true"></i><input class="form-control auth-password" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required><button type="button" class="password-toggle" aria-label="Show password" aria-controls="password" aria-pressed="false" hidden><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" value="1" id="remember" @checked(old('remember'))><label class="form-check-label" for="remember">Remember Me</label></div>
    <button class="btn btn-primary w-100 auth-submit">Login <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button>
</form>
<p class="auth-provisioning">Accounts are provided by your ICT Administrator.</p>
<div class="auth-security-note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Authorized UPTM users only. Access is protected by secure authentication.</span></div>
@endsection
