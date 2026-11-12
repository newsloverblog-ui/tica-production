<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <title>{{ __('Page not found') }} | TICA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>
    *{box-sizing:border-box}body{margin:0;color:#0b2447;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.site-header{display:flex;align-items:center;justify-content:space-between;min-height:68px;padding:0 max(24px,calc((100vw - 1200px)/2));border-bottom:1px solid #e4e8f0}.logo{color:#0b2447;font-size:26px;font-weight:800;letter-spacing:-.06em;text-decoration:none}.discover{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 20px;color:#fff;background:#1f5fcc;border-radius:999px;font-size:13px;font-weight:800;text-decoration:none}.discover:hover{background:#174da8}.error-shell{display:grid;min-height:calc(100vh - 68px);padding:64px 24px;place-items:center;text-align:center}.error-shell .code{max-width:none;margin:0;color:#f97316;font-size:clamp(84px,15vw,160px);font-weight:800;letter-spacing:-.08em;line-height:.8}.error-shell h1{max-width:700px;margin:30px auto 0;font-size:clamp(32px,5vw,54px);letter-spacing:-.045em;line-height:1.05}.error-shell p{max-width:540px;margin:18px auto 0;color:#667085;font-size:16px;line-height:1.7}.error-shell .discover{margin-top:30px}@media(max-width:640px){.site-header{padding-inline:18px}.error-shell{align-items:start;padding-top:112px}}
  </style>
</head>
<body>
  <header class="site-header"><a class="logo" href="{{ url('/') }}">TICA</a><a class="discover" href="{{ url('/events') }}">{{ __('Discover events') }}</a></header>
  <main class="error-shell"><div><p class="code">404</p><h1>{{ __('We can’t find that page') }}</h1><p>{{ __('The link may have moved, or the event may no longer be available.') }}</p><a class="discover" href="{{ url('/events') }}">{{ __('Discover events') }}</a></div></main>
</body>
</html>
