<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ __('Verify your bookings') }}</title>
  <link rel="manifest" crossorigin="use-credentials" href="{{ route('manifest.scanner') }}" />
  <link rel="shortcut icon" type="image/png" href="{{ asset('assets/admin/img/' . $websiteInfo->favicon) }}">
  <link href="{{ asset('assets/pwa_scanner/bootstrap.min.css') }}" rel="stylesheet">
  @include('organizer.pwa.partials.vars')
  <script src="{{ asset('assets/pwa_scanner/html5-qrcode.min.js') }}"></script>
  <script src="{{ asset('assets/pwa_scanner/pwa.js') }}" defer></script>
  <script src="{{ asset('assets/pwa_scanner/jquery.min.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/front/css/toastr.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/pwa_scanner/style.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/organizer-blend.css') }}?v={{ @filemtime(public_path('assets/tica-ui/css/organizer-blend.css')) }}">
</head>

<body class="tica-organizer tica-scanner-page">
  <header class="scanner-shell-header">
    <div class="scanner-shell-header__inner">
      <a class="scanner-brand" href="{{ Auth::guard('organizer')->check() ? url('/dashboard') : url('/checkin') }}" aria-label="{{ __('Back to the TICA workspace') }}"><img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt="TICA"></a>
      <a class="scanner-back" href="{{ Auth::guard('organizer')->check() ? url('/dashboard') : url('/checkin') }}">{{ Auth::guard('organizer')->check() ? __('Back to dashboard') : __('Back to check-in') }}</a>
      <div class="scanner-shell-status"><span class="scanner-status-dot" aria-hidden="true"></span>{{ __('Secure check-in workspace') }}</div>
    </div>
  </header>
  <div>
    <div class="container">
      <section class="scanner-intro mx-auto">
        <p class="scanner-eyebrow mb-2">{{ __('Event operations') }}</p>
        <h1>{{ __('Fast entry. Clear decisions.') }}</h1>
        <p>{{ __('Enroll this device, prepare your event and scan every guest with live duplicate protection.') }}</p>
        <nav class="scanner-jump-links" aria-label="{{ __('Scanner sections') }}">
          <a href="#scanner-device">{{ __('Device') }}</a>
          <a href="#scanner-camera">{{ __('Scan') }}</a>
          <a href="#scanner-offline">{{ __('Offline') }}</a>
          @if(Auth::guard('organizer')->check())<a href="#scanner-staff">{{ __('Staff') }}</a>@endif
        </nav>
      </section>
      @if(Auth::guard('scanner')->check())
        <div class="d-flex justify-content-between align-items-center mb-3 mx-auto" style="width:100%;max-width:760px;">
          <div><strong>{{ Auth::guard('scanner')->user()->name }}</strong><br><small class="text-muted">{{ Auth::guard('scanner')->user()->role === 'supervisor' ? __('Gate supervisor') : __('Check-in staff') }}</small></div>
          <form method="POST" action="{{ route('scanner.logout') }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('Sign out') }}</button></form>
        </div>
      @endif
      @include('organizer.pwa.partials.panels')
    </div>
  </div>
  {{-- sweet alert --}}
  <script type="text/javascript" src="{{ asset('assets/admin/js/sweetalert.min.js') }}"></script>


  <script src="{{ asset('assets/pwa_scanner/script.js') }}"></script>
</body>

</html>
