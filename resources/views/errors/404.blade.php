@php
  $adminSignedIn = auth('admin')->check();
  $organizerSignedIn = auth('organizer')->check();
  $customerSignedIn = auth('customer')->check();
  $primaryUrl = $adminSignedIn ? route('admin.dashboard') : ($organizerSignedIn ? url('/dashboard') : url('/discover'));
  $primaryLabel = $adminSignedIn ? __('Back to admin') : ($organizerSignedIn ? __('Back to dashboard') : __('Discover events'));
  $ticaBrand = \App\Support\TicaSite::get('branding');
  $ticaFavicon = !empty($ticaBrand['favicon']) ? \App\Support\TicaSite::imageUrl($ticaBrand['favicon']) : null;
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <meta name="theme-color" content="{{ $ticaBrand['secondary_color'] }}">
  <title>{{ __('Page not found') }} | {{ $ticaBrand['site_name'] }}</title>
  @if($ticaFavicon)<link rel="icon" href="{{ $ticaFavicon }}">@endif
  <link rel="preload" href="{{ asset('assets/tica-ui/fonts/montserrat-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/tica.css') }}?v={{ tica_asset_version('assets/tica-ui/css/tica.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/human-ui.css') }}?v={{ tica_asset_version('assets/tica-ui/css/human-ui.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/typography.css') }}?v={{ tica_asset_version('assets/tica-ui/css/typography.css') }}">
  <style>:root{--tica-brand-primary:{{ $ticaBrand['primary_color'] }};--tica-brand-deep:{{ $ticaBrand['secondary_color'] }};--tica-brand-accent:{{ $ticaBrand['accent_color'] }};--indigo-2:var(--tica-brand-primary);--indigo:var(--tica-brand-deep);--ink:var(--tica-brand-deep);--camwood:var(--tica-brand-accent)}</style>
</head>
<body class="tica-app-ui tica-missing-page {{ $organizerSignedIn ? 'isorg' : '' }} {{ $customerSignedIn ? 'isuser' : '' }}">
  @include('tica-ui.partials.header')

  <main id="main" tabindex="-1">
    <section class="page missing-page" data-page="missing">
      <div class="wrap missing-hero">
        <div class="missing-copy">
          <h1 class="display">{{ __("We can't find that page") }}</h1>
          <p class="lead">{{ __("Check the address, or go see what's coming up.") }}</p>
          <div class="missing-actions">
            <a class="missing-cta" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>
            @if ($adminSignedIn || $organizerSignedIn)<a class="missing-cta secondary" href="{{ url('/discover') }}">{{ __('Discover events') }}</a>@endif
          </div>
        </div>
      </div>
    </section>
  </main>

  @include('tica-ui.partials.footer')
  <script>
    (function () {
      var topbar = document.querySelector('.topbar');
      if (!topbar) return;
      function updateHeader() { topbar.classList.toggle('scrolled', window.scrollY > 8); }
      updateHeader();
      window.addEventListener('scroll', updateHeader, { passive: true });
    }());
  </script>
</body>
</html>
