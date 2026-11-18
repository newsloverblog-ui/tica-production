@php
  $adminSignedIn = auth('admin')->check();
  $organizerSignedIn = auth('organizer')->check();
  $primaryUrl = $adminSignedIn ? route('admin.dashboard') : ($organizerSignedIn ? url('/dashboard') : url('/discover'));
  $primaryLabel = $adminSignedIn ? __('Back to admin') : ($organizerSignedIn ? __('Back to dashboard') : __('Discover events'));
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <title>{{ __('Page not found') }} | TICA</title>
  <style>
    :root{--navy:#0b2447;--blue:#2563d9;--blue-dark:#1d4fb7;--orange:#f59a45;--line:#e4eaf3;--muted:#64748b;--soft:#f6f8fc}
    *{box-sizing:border-box}body{margin:0;color:var(--navy);background:var(--soft);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased}
    .site-header{display:flex;align-items:center;justify-content:space-between;min-height:76px;padding:0 max(24px,calc((100vw - 1180px)/2));border-bottom:1px solid var(--line);background:#fff}
    .logo{display:inline-flex;align-items:center}.logo img{display:block;width:74px;height:auto}.header-link{color:var(--navy);font-size:14px;font-weight:700;text-decoration:none}.header-link:hover{color:var(--blue)}
    .error-shell{display:grid;min-height:calc(100vh - 76px);padding:48px 24px;place-items:center}.error-card{width:min(680px,100%);padding:clamp(38px,7vw,72px);border:1px solid var(--line);border-radius:24px;background:#fff;box-shadow:0 22px 70px rgba(11,36,71,.08);text-align:center}
    .code{display:inline-flex;align-items:center;gap:12px;margin:0;color:var(--navy);font-size:clamp(72px,13vw,124px);font-weight:800;letter-spacing:-.08em;line-height:.8}.code:after{width:11px;height:44px;border-radius:8px;background:var(--orange);content:""}
    h1{margin:32px auto 0;font-size:clamp(29px,4vw,42px);letter-spacing:-.035em;line-height:1.08}p{max-width:500px;margin:16px auto 0;color:var(--muted);font-size:16px;line-height:1.65}
    .actions{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:30px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border:1px solid var(--blue);border-radius:12px;color:#fff;background:var(--blue);font-size:14px;font-weight:800;text-decoration:none;transition:transform .18s ease,background .18s ease}.button:hover{background:var(--blue-dark);transform:translateY(-1px)}.button--quiet{border-color:var(--line);color:var(--navy);background:#fff}.button--quiet:hover{border-color:#c9d5e6;background:#f8fafc}
    @media(max-width:640px){.site-header{min-height:68px;padding-inline:18px}.logo img{width:65px}.error-shell{min-height:calc(100vh - 68px);padding:24px 16px}.error-card{padding:44px 22px;border-radius:20px}.actions{display:grid}.button{width:100%}}
  </style>
</head>
<body>
  <header class="site-header">
    <a class="logo" href="{{ url('/') }}" aria-label="TICA home"><img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt="TICA"></a>
    <a class="header-link" href="{{ url('/discover') }}">{{ __('Discover events') }}</a>
  </header>
  <main class="error-shell">
    <section class="error-card" aria-labelledby="error-title">
      <p class="code" aria-label="Error 404">404</p>
      <h1 id="error-title">{{ __('We can’t find that page') }}</h1>
      <p>{{ __('The address may be incorrect, or the page may have moved.') }}</p>
      <div class="actions">
        <a class="button" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>
        @if ($adminSignedIn || $organizerSignedIn)<a class="button button--quiet" href="{{ url('/discover') }}">{{ __('Discover events') }}</a>@endif
      </div>
    </section>
  </main>
</body>
</html>
