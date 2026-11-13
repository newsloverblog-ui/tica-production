<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Not part of your role') }} | TICA</title>
    <style>
      body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F5F7FC;color:#0F172A;font:400 16px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;padding:24px;box-sizing:border-box}
      .box{max-width:460px;width:100%;background:#fff;border:1px solid #E2E8F1;border-radius:16px;padding:32px;text-align:center}
      .ic{width:56px;height:56px;margin:0 auto 16px;border-radius:14px;background:#FEF3E8;color:#B4682E;display:grid;place-items:center}
      .ic svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
      h1{margin:0 0 8px;font-size:22px;font-weight:800}
      p{margin:0 0 24px;color:#475569}
      a{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 22px;border-radius:12px;background:#2E4FD8;color:#fff;font-weight:700;text-decoration:none}
      a:hover{background:#1E3AB0}
      a:focus-visible{outline:3px solid rgba(46,79,216,.35);outline-offset:2px}
    </style>
  </head>
  <body>
    <main class="box">
      <div class="ic" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></div>
      <h1>{{ __('This isn\'t part of your role') }}</h1>
      <p>{{ __('Your :role access doesn\'t include this page. Ask the account owner if you need it.', ['role' => ucfirst($role)]) }}</p>
      <a href="{{ $backUrl }}">{{ __('Back to Overview') }}</a>
    </main>
  </body>
</html>
