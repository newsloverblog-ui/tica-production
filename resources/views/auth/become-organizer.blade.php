<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{{ __('Become an organizer') }} · TICA</title>
  <style>
    :root{font-family:var(--tica-font-ui);color:#0B2447;--navy:#0B2447;--blue:#2E4FD8;--blue-h:#2541B8;--soft:#EEF2FE;--line:#E3E8F0;--muted:#526079;--orange:#F2A06B;--bad:#A3352B;--bad-soft:#FDF0EE;--ok:#12613A;--ok-soft:#E9F7EF}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#F4F6FA}
    .shell{width:min(1040px,calc(100% - 32px));margin:auto}
    .top{height:72px;display:flex;align-items:center;border-bottom:1px solid var(--line);background:#fff}
    .top .shell{display:flex;align-items:center;justify-content:space-between;gap:16px}
    .top img{display:block;height:24px;width:auto}
    .top a.back{color:var(--muted);font-size:14px;font-weight:600;text-decoration:none}
    .top a.back:hover{color:var(--navy)}
    .layout{display:grid;grid-template-columns:minmax(0,600px) 300px;gap:20px;justify-content:center;align-items:start;padding:48px 0 64px}
    .card{background:#fff;border:1px solid var(--line);border-radius:18px}
    .main{padding:32px}.side{padding:24px}
    h1{margin:0;font-size:28px;line-height:1.2;letter-spacing:-.02em}
    .intro{margin:10px 0 0;color:var(--muted);line-height:1.6}
    .note{margin:22px 0 0;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.5}
    .note.bad{background:var(--bad-soft);color:var(--bad)}
    .note.ok{background:var(--ok-soft);color:var(--ok)}
    .note.info{background:var(--soft);color:#1E3A8A}
    form{margin-top:24px;display:grid;gap:18px}
    .field{display:grid;gap:6px}
    .field label{font-size:14px;font-weight:600}
    .field small{color:var(--muted);font-size:13px;line-height:1.45}
    .field input{width:100%;min-height:48px;border:1px solid #CBD5E4;border-radius:10px;padding:0 14px;background:#fff;color:var(--navy);font:inherit;font-size:15px}
    .field input:focus{outline:3px solid rgba(46,79,216,.18);border-color:var(--blue)}
    .field input[aria-invalid=true]{border-color:var(--bad)}
    .err{margin:0;color:var(--bad);font-size:13px}
    fieldset{margin:0;padding:18px;border:1px solid var(--line);border-radius:14px;display:grid;gap:12px}
    legend{padding:0 6px;font-size:14px;font-weight:700}
    .or{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px}
    .or:before,.or:after{content:"";flex:1;height:1px;background:var(--line)}
    .g{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;border:1px solid #CBD5E4;border-radius:10px;background:#fff;color:var(--navy);font-weight:600;font-size:15px;text-decoration:none}
    .g:hover{border-color:var(--blue)}
    .g svg{width:18px;height:18px}
    .done{display:flex;align-items:center;gap:10px;color:var(--ok);font-weight:600;font-size:15px}
    .done svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
    .actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:4px}
    .btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;border:0;border-radius:10px;padding:0 22px;background:var(--blue);color:#fff;font:inherit;font-weight:700;font-size:15px;cursor:pointer;text-decoration:none}
    .btn:hover{background:var(--blue-h)}
    .link{color:var(--muted);font-weight:600;font-size:15px;text-decoration:none;padding:12px 8px}
    .link:hover{color:var(--navy)}
    .btn:focus-visible,.link:focus-visible,.g:focus-visible,.back:focus-visible{outline:3px solid rgba(46,79,216,.3);outline-offset:2px}
    .side h2{margin:0 0 14px;font-size:16px}
    .side ul{margin:0;padding:0;list-style:none;display:grid;gap:14px}
    .side li{display:flex;gap:10px;font-size:14px;line-height:1.5;color:var(--muted)}
    .side li svg{flex:none;width:18px;height:18px;margin-top:2px;fill:none;stroke:var(--blue);stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
    .side li b{color:var(--navy);font-weight:600}
    .accent{width:34px;height:3px;margin-bottom:16px;border-radius:99px;background:var(--orange)}
    @media(max-width:820px){.layout{grid-template-columns:1fr;padding:24px 0 40px}.main{padding:24px}}
    @media(prefers-reduced-motion:no-preference){.card{animation:enter .25s ease-out both}@keyframes enter{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}}
  </style>
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/typography.css') }}?v={{ tica_asset_version('assets/tica-ui/css/typography.css') }}">
</head>
<body>
  <header class="top"><div class="shell"><a href="/" aria-label="TICA home"><img src="/assets/tica-ui/img/tica-logo.png" alt="TICA"></a><a class="back" href="/account">{{ __('Back to your account') }}</a></div></header>
  <main class="shell layout">
    <section class="card main" aria-labelledby="bo-title">
      <h1 id="bo-title">{{ __('Become an organizer') }}</h1>
      <p class="intro">{{ __('Sell tickets to your own events with the same email. Your past tickets and orders stay with you.') }}</p>

      @if (session('alert'))<p class="note bad" role="alert">{{ session('alert') }}</p>@endif
      @if (session('success'))<p class="note ok" role="status">{{ session('success') }}</p>@endif

      @if ($pending)
        <p class="note info" role="status">{{ __('Your request for :org is under review. We\'ll email you once it\'s decided.', ['org' => $pending->organisation]) }}</p>
        <div class="actions" style="margin-top:22px"><a class="btn" href="/account">{{ __('Back to your account') }}</a></div>
      @else
        <p class="note info">{{ $needsApproval
          ? __('A TICA admin reviews each request. You can keep using your account while you wait.')
          : __('Your account switches to an organizer account straight away. From then on, log in as an organizer.') }}</p>

        <form method="post" action="{{ route('customer.organizer_upgrade.store') }}" novalidate>
          @csrf
          <div class="field">
            <label for="organisation">{{ __('Organisation or brand name') }}</label>
            <input id="organisation" name="organisation" type="text" maxlength="100" required value="{{ old('organisation') }}" @error('organisation') aria-invalid="true" aria-describedby="organisation-e" @enderror>
            @error('organisation')<p class="err" id="organisation-e">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="contact_name">{{ __('Your full name') }}</label>
            <input id="contact_name" name="contact_name" type="text" maxlength="200" required autocomplete="name" value="{{ old('contact_name', trim($customer->fname . ' ' . $customer->lname)) }}" @error('contact_name') aria-invalid="true" aria-describedby="contact_name-e" @enderror>
            @error('contact_name')<p class="err" id="contact_name-e">{{ $message }}</p>@enderror
          </div>
          <div class="field">
            <label for="phone">{{ __('Phone number') }}</label>
            <input id="phone" name="phone" type="tel" maxlength="20" required autocomplete="tel" value="{{ old('phone', $customer->phone) }}" @error('phone') aria-invalid="true" aria-describedby="phone-e" @enderror>
            @error('phone')<p class="err" id="phone-e">{{ $message }}</p>@enderror
          </div>

          <fieldset>
            <legend>{{ __('Confirm it\'s you') }}</legend>
            @if ($googleConfirmed)
              <p class="done"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>{{ __('Confirmed with Google') }}</p>
            @else
              <div class="field">
                <label for="current_password">{{ __('Current password') }}</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" @error('current_password') aria-invalid="true" aria-describedby="current_password-e" @enderror>
                @error('current_password')<p class="err" id="current_password-e">{{ $message }}</p>@enderror
              </div>
              @if ($googleOnly && $googleReady)
                <p class="or">{{ __('or') }}</p>
                <a class="g" href="{{ route('tica.auth.google', ['as' => 'upgrade']) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.6a4.8 4.8 0 0 1-2.1 3.1v2.6h3.4c2-1.8 3.1-4.5 3.1-7.6z"/><path fill="#34A853" d="M12 22.5c2.8 0 5.2-.9 6.9-2.5l-3.4-2.6c-.9.6-2.1 1-3.5 1-2.7 0-5-1.8-5.8-4.3H3.2v2.7a10.5 10.5 0 0 0 8.8 5.7z"/><path fill="#FBBC05" d="M6.2 14.1a6.3 6.3 0 0 1 0-4.1V7.3H3.2a10.5 10.5 0 0 0 0 9.4z"/><path fill="#EA4335" d="M12 5.7c1.5 0 2.9.5 4 1.6l3-3A10.5 10.5 0 0 0 3.2 7.3l3 2.7c.8-2.5 3.1-4.3 5.8-4.3z"/></svg>{{ __('Confirm with Google') }}</a>
              @endif
            @endif
          </fieldset>

          <div class="actions">
            <button class="btn" type="submit">{{ $needsApproval ? __('Send request') : __('Become an organizer') }}</button>
            <a class="link" href="/account">{{ __('Cancel') }}</a>
          </div>
        </form>
      @endif
    </section>

    <aside class="card side" aria-labelledby="bo-what">
      <div class="accent"></div>
      <h2 id="bo-what">{{ __('What changes') }}</h2>
      <ul>
        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span><b>{{ __('Same email and password.') }}</b> {{ __('You keep the details you log in with today.') }}</span></li>
        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span><b>{{ __('Log in as an organizer.') }}</b> {{ __('Your customer log-in switches off once you are upgraded.') }}</span></li>
        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span><b>{{ __('Your tickets stay.') }}</b> {{ __('Past tickets and orders appear under My tickets in your organizer dashboard.') }}</span></li>
      </ul>
    </aside>
  </main>
</body>
</html>
