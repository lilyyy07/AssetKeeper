@extends('auth.layout')
@section('title', 'Verify Your Identity')
@section('content')
<div class="mb-4"><x-brand-logo size="medium"/></div>
<p class="auth-eyebrow"><i class="bi bi-shield-lock" aria-hidden="true"></i> EMAIL VERIFICATION</p>
<h2>Verify Your Identity</h2><p class="auth-intro">We've sent a 6-digit verification code to your registered email address.</p>
<p class="auth-destination">{{ $maskedEmail }} <span>Code expires after 5 minutes.</span></p>
<form method="post" action="{{ route('mfa.verify') }}">@csrf<label class="form-label" for="code">Verification Code</label><input class="form-control otp-input mb-3" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" placeholder="------" aria-describedby="otp-help" required autofocus><p class="small text-muted" id="otp-help">Enter all six digits. Only the latest code will work.</p><button class="btn btn-primary w-100 auth-submit">Verify <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button></form>
<form method="post" action="{{ route('mfa.resend') }}" class="mt-3">@csrf<button class="btn btn-outline-primary w-100">Resend Code</button><p class="small text-muted mt-2">Please wait 60 seconds between requests.</p></form>
<form method="post" action="{{ route('logout') }}" class="text-center">@csrf<button class="btn btn-link auth-cancel">Cancel / Sign Out</button></form>
<div class="auth-security-note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Never share your verification code. ICT staff will never ask you for it.</span></div>
@endsection
