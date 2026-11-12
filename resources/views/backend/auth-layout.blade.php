<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ $websiteInfo->website_title }}</title>
    <link rel="shortcut icon" type="image/png" href="{{ asset('assets/admin/img/' . $websiteInfo->favicon) }}">
    <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-auth.css') }}?v={{ file_exists(public_path('assets/tica-ui/css/admin-auth.css')) ? filemtime(public_path('assets/tica-ui/css/admin-auth.css')) : '3' }}">
  </head>
  <body class="taa">
    <aside class="taa-brand" aria-hidden="true">
      <a class="taa-logo" href="{{ url('/') }}" tabindex="-1"><img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt=""></a>
      <div class="taa-art">
        <svg viewBox="0 0 560 440" aria-hidden="true">
          <circle cx="290" cy="220" r="190" fill="#DFE6FB"/>
          <circle cx="470" cy="90" r="40" fill="#FDE7D3"/>
          <circle cx="96" cy="350" r="26" fill="#FDE7D3"/>
          <rect x="120" y="96" width="320" height="230" rx="22" fill="#FFFFFF" stroke="#D5DDF6" stroke-width="2"/>
          <circle cx="150" cy="126" r="6" fill="#F3A35B"/>
          <circle cx="168" cy="126" r="6" fill="#C9D4F7"/>
          <circle cx="186" cy="126" r="6" fill="#C9D4F7"/>
          <rect x="148" y="156" width="110" height="12" rx="6" fill="#0F172A" opacity="0.85"/>
          <rect x="148" y="178" width="72" height="9" rx="4.5" fill="#B6C2E8"/>
          <rect x="330" y="152" width="84" height="34" rx="17" fill="#EAF0FF"/>
          <path d="M346 175 l8 -8 l6 6 l10 -10" fill="none" stroke="#2E4FD8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          <rect x="378" y="164" width="24" height="9" rx="4.5" fill="#2E4FD8"/>
          <rect x="152" y="252" width="26" height="46" rx="7" fill="#C9D4F7"/>
          <rect x="192" y="228" width="26" height="70" rx="7" fill="#9DB0F0"/>
          <rect x="232" y="240" width="26" height="58" rx="7" fill="#C9D4F7"/>
          <rect x="272" y="208" width="26" height="90" rx="7" fill="#2E4FD8"/>
          <rect x="312" y="222" width="26" height="76" rx="7" fill="#9DB0F0"/>
          <rect x="352" y="196" width="26" height="102" rx="7" fill="#F3A35B"/>
          <rect x="392" y="236" width="26" height="62" rx="7" fill="#C9D4F7"/>
          <g transform="rotate(-12 150 330)">
            <path d="M60 300 h170 v18 a14 14 0 0 0 0 28 v18 h-170 v-18 a14 14 0 0 0 0 -28 z" fill="#F08A24"/>
            <line x1="180" y1="306" x2="180" y2="358" stroke="#FFFFFF" stroke-width="3" stroke-dasharray="5 6" opacity="0.8"/>
            <rect x="82" y="318" width="76" height="10" rx="5" fill="#FFFFFF" opacity="0.95"/>
            <rect x="82" y="336" width="50" height="8" rx="4" fill="#FFFFFF" opacity="0.7"/>
            <circle cx="204" cy="332" r="9" fill="#FFFFFF" opacity="0.9"/>
          </g>
          <circle cx="446" cy="320" r="34" fill="#2E4FD8" stroke="#FFFFFF" stroke-width="5"/>
          <path d="M432 320 l10 10 l18 -20" fill="none" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M100 120 v20 M90 130 h20" stroke="#2E4FD8" stroke-width="3" stroke-linecap="round"/>
          <path d="M486 196 v14 M479 203 h14" stroke="#F08A24" stroke-width="3" stroke-linecap="round"/>
          <circle cx="520" cy="250" r="5" fill="#9DB0F0"/>
          <circle cx="70" cy="210" r="5" fill="#F3A35B"/>
          <circle cx="300" cy="400" r="6" fill="#9DB0F0"/>
        </svg>
      </div>
      <div class="taa-pitch">
        <h2>{{ __('Manage events for the Kulture.') }}</h2>
      </div>
    </aside>
    <main class="taa-main">
      <div class="taa-top"><a href="{{ url('/') }}">{{ __('Back to tica.ng') }}</a></div>
      <div class="taa-card">
        <a class="taa-logo-sm" href="{{ url('/') }}"><img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt="TICA"></a>
        @foreach (['alert' => 'bad', 'warning' => 'bad', 'success' => 'ok'] as $key => $tone)
          @if (session()->has($key))
            <p class="taa-msg {{ $tone }}" role="{{ $tone === 'ok' ? 'status' : 'alert' }}">{{ __(session($key)) }}</p>
          @endif
        @endforeach
        @yield('form')
      </div>
      <p class="taa-foot">{{ __('Admin access only.') }}</p>
    </main>
  </body>
</html>
