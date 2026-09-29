<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') | AssetKeeper</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand-mark.svg') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/assetkeeper.css') }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-branding" aria-label="About AssetKeeper">
        <div class="auth-brand-top"><x-brand-logo size="medium" variant="light"/><span class="auth-internal">INTERNAL SYSTEM</span></div>
        <div class="auth-brand-message"><p class="auth-eyebrow">UPTM ICT DEPARTMENT</p><h1>Every asset.<br>One secure place.</h1><h2>IT Asset Tracking &amp; Inventory Management System</h2><p>Manage, track and monitor ICT assets securely in one centralized system.</p></div>
        <div class="auth-illustration" aria-hidden="true"><div class="asset-map-header"><span><i class="bi bi-grid-1x2"></i> Asset workspace</span><span class="secure-pill"><i class="bi bi-shield-check"></i> Protected</span></div><div class="asset-map-grid"><div><i class="bi bi-laptop"></i><span>Devices</span><b>Track &amp; allocate</b></div><div><i class="bi bi-hdd-network"></i><span>Infrastructure</span><b>Monitor &amp; maintain</b></div><div><i class="bi bi-box-seam"></i><span>Inventory</span><b>Organize &amp; report</b></div></div><div class="asset-map-footer"><span class="connection-dot"></span> Connected records. Clear accountability.</div></div>
        <p class="auth-brand-footer"><i class="bi bi-shield-lock" aria-hidden="true"></i> Built for secure ICT asset administration</p>
    </section>
    <section class="auth-form-panel" aria-label="Account access">
        <div class="auth-mobile-brand"><x-brand-logo size="small"/><span>UPTM ICT Department</span></div>
        <div class="auth-card">@include('partials.alerts')@yield('content')</div>
        <footer class="auth-footer">&copy; {{ now()->year }} AssetKeeper &bull; UPTM ICT Department</footer>
    </section>
</main>
<script src="{{ asset('js/auth.js') }}" defer></script>
</body></html>
