<!DOCTYPE html>
<html>
  <head>
    {{-- required meta tags --}}
    <meta http-equiv="Content-Type" content="text/html" charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    {{-- csrf-token for ajax request --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- title --}}
    <title>{{ __('Organizer') . ' | ' . $websiteInfo->website_title }}</title>

    {{-- fav icon --}}
    <link rel="shortcut icon" type="image/png" href="{{ asset('assets/admin/img/' . $websiteInfo->favicon) }}">

    {{-- include styles --}}
    @includeIf('organizer.partials.styles')

    {{-- additional style --}}
    @yield('style')
  </head>

  <body class="tica-control-center tica-organizer orgshell-legacy t-app" data-background-color="white">
    <a class="tica-skip-link" href="#tica-main-content">{{ __('Skip to main content') }}</a>
    {{-- loader start --}}
    <div class="request-loader">
      <img src="{{ asset('assets/admin/img/loader.gif') }}" alt="loader">
    </div>
    {{-- loader end --}}

    <div class="wrapper">
      {{-- The one organiser shell, shared with the TICA app pages. --}}
      @php
        $shellActive = request()->routeIs('organizer.event_management.*', 'organizer.event.*', 'organizer.add.event.*', 'choose-event-type', 'organizer.ticket_management.*') ? 'events'
          : (request()->routeIs('organizer.withdraw*', 'organizer.payout*') ? 'payouts'
          : (request()->routeIs('organizer.event_booking.index', 'organizer.event_booking.details', 'organizer.event-booking*') && !request()->routeIs('organizer.event_booking.report') ? 'orders' : 'tools'));
      @endphp
      <x-shell.organizer :active="$shellActive" :title="trim($__env->yieldContent('page-title')) ?: null" />

      <div class="main-panel">
        <div class="content">
          <main class="page-inner" id="tica-main-content" tabindex="-1">
            @yield('content')
          </main>
        </div>

        {{-- footer area start --}}
        @includeIf('organizer.partials.footer')
        {{-- footer area end --}}
      </div>
    </div>

    {{-- include scripts --}}
    @includeIf('organizer.partials.scripts')
    @includeIf('organizer.partials.modal')
  </body>
</html>
