<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verify your sign-in · TICA</title>
  <style>
    :root{font-family:var(--tica-font-ui);color:#102342;background:#f4f7fb;--navy:#102342;--blue:#2d63c8;--blue-soft:#edf3ff;--line:#dce5f1;--muted:#65748a;--orange:#df8739;--danger:#a53c35;--danger-soft:#fff1ef}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f4f7fb}.shell{width:min(1040px,calc(100% - 40px));margin:auto}.top{height:76px;display:flex;align-items:center;border-bottom:1px solid var(--line);background:rgba(255,255,255,.88)}.brand{display:flex;align-items:center;gap:11px;color:var(--navy);font-weight:850;text-decoration:none}.brand-mark{display:grid;place-items:center;width:37px;height:37px;border-radius:11px;background:var(--blue);color:#fff;font-size:14px;letter-spacing:-.04em}.layout{display:grid;grid-template-columns:minmax(0,590px) 280px;gap:20px;justify-content:center;align-items:start;padding:72px 0}.card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 14px 40px rgba(18,48,92,.055)}.main{padding:36px}.side{padding:24px}.icon{display:grid;place-items:center;width:54px;height:54px;border-radius:16px;background:var(--blue-soft);color:var(--blue)}.icon svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.8}.eyebrow{margin:22px 0 8px;color:var(--blue);font-size:12px;font-weight:900;letter-spacing:.11em;text-transform:uppercase}h1{margin:0;font-size:32px;line-height:1.15;letter-spacing:-.035em}.intro{margin:12px 0 0;color:var(--muted);line-height:1.6}.err{margin:22px 0 0;padding:12px 14px;border-radius:11px;background:var(--danger-soft);color:var(--danger);font-size:14px}.field{display:grid;gap:8px;margin:26px 0 16px}.field label{font-size:13px;font-weight:850}.field small{color:var(--muted);line-height:1.45}.field input{width:100%;height:54px;border:1px solid #cbd7e7;border-radius:11px;padding:0 16px;background:#fff;color:var(--navy);font:800 20px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.14em}.field input:focus{outline:3px solid rgba(45,99,200,.16);border-color:var(--blue)}.btn{display:inline-flex;width:100%;min-height:50px;align-items:center;justify-content:center;border:1px solid transparent;border-radius:11px;padding:0 18px;background:var(--blue);color:#fff;font-weight:850;cursor:pointer}.btn:hover{background:#2455ad}.cancel{display:block;width:100%;margin-top:12px;border:0;background:none;color:#60718a;font-weight:750;cursor:pointer}.btn:focus-visible,.cancel:focus-visible{outline:3px solid rgba(45,99,200,.2);outline-offset:2px}.side h2{margin:0 0 14px;font-size:16px}.side p{margin:0;color:var(--muted);font-size:13px;line-height:1.55}.help{margin-top:20px;padding-top:18px;border-top:1px solid var(--line)}.help b{display:block;margin-bottom:5px;font-size:13px}.accent{width:34px;height:3px;margin-bottom:18px;border-radius:99px;background:var(--orange)}@media(max-width:760px){.shell{width:min(100% - 28px,590px)}.top{height:64px}.layout{grid-template-columns:1fr;padding:28px 0}.main{padding:24px}.side{order:2}}@media(prefers-reduced-motion:no-preference){.card{animation:enter .28s ease-out both}@keyframes enter{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}}
  </style>
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/typography.css') }}?v={{ tica_asset_version('assets/tica-ui/css/typography.css') }}">
</head>
<body>
  <header class="top"><div class="shell"><a class="brand" href="/"><span class="brand-mark">tica</span><span>Secure sign-in</span></a></div></header>
  <main class="shell layout">
    <section class="card main">
      <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l7 3v5c0 4.6-2.7 8-7 10-4.3-2-7-5.4-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg></div>
      <p class="eyebrow">One more step</p>
      @if($method === 'email')
        <h1>Check your email</h1>
        <p class="intro">Enter the 6-digit code we sent you. It expires in 10 minutes and works only once.</p>
      @else
        <h1>Enter your verification code</h1>
        <p class="intro">Open your authenticator app and enter the current 6-digit code. You can also use one unused recovery code.</p>
      @endif
      @if($errors->any())<p class="err" role="alert">{{ $errors->first() }}</p>@endif
      <form method="post" action="{{ route('mfa.login.verify') }}">
        @csrf
        <div class="field">
          <label for="code">{{ $method === 'email' ? 'Email security code' : 'Authenticator or recovery code' }}</label>
          <input id="code" name="code" type="text" inputmode="{{ $method === 'email' ? 'numeric' : 'text' }}" maxlength="32" autocomplete="one-time-code" autofocus required>
          @if($method !== 'email')<small>Codes refresh every 30 seconds. A recovery code can be used only once.</small>@endif
        </div>
        <button class="btn" type="submit">Verify and sign in</button>
      </form>
      <form method="post" action="{{ route('mfa.login.cancel') }}">@csrf<button class="cancel" type="submit">Cancel sign-in</button></form>
    </section>
    <aside class="card side">
      <div class="accent"></div>
      <h2>Protect your code</h2>
      <p>TICA support will never ask you to share an authenticator or recovery code.</p>
      <div class="help"><b>Lost access?</b><p>Use one of the recovery codes you saved when you turned on two-step verification.</p></div>
    </aside>
  </main>
</body>
</html>
