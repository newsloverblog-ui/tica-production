<!DOCTYPE html>
<html lang="zxx" dir="{{ $currentLanguageInfo->direction == 1 ? 'rtl' : 'ltr' }}">

<head>
  @php
    $siteTitle = trim($websiteInfo->website_title ?? 'Tica');
    $pageTitle = trim($__env->yieldContent('pageHeading'));
    $resolvedTitle = $pageTitle !== '' ? $pageTitle . ' | ' . $siteTitle : $siteTitle;
    $resolvedDescription = trim(strip_tags($__env->yieldContent('meta-description')));
    if ($resolvedDescription === '') {
        $resolvedDescription = trim(strip_tags($seo->meta_description_home ?? 'Discover and book memorable events with Tica.'));
    }
    $resolvedKeywords = trim(strip_tags($__env->yieldContent('meta-keywords')));
    if ($resolvedKeywords === '') {
        $resolvedKeywords = trim(strip_tags($seo->meta_keyword_home ?? 'events, tickets, experiences, Tica'));
    }
    $resolvedOgTitle = trim(strip_tags($__env->yieldContent('og-title'))) ?: $resolvedTitle;
    $resolvedOgDescription = trim(strip_tags($__env->yieldContent('og-description'))) ?: $resolvedDescription;
    $resolvedOgImage = trim($__env->yieldContent('og-image')) ?: asset('assets/front/images/home/organizer-live-event-unsplash.jpg');
    $canonicalUrl = url()->current();
  @endphp
  <!-- Required meta tags -->
  <meta charset="utf-8" />
  <meta http-equiv="x-ua-compatible" content="ie=edge" />
  <meta name="description" content="{{ $resolvedDescription }}">
  <meta name="keywords" content="{{ $resolvedKeywords }}">
  <meta name="robots" content="@yield('robots', 'index,follow')">
  <link rel="canonical" href="{{ $canonicalUrl }}">

  <meta property="og:type" content="@yield('og-type', 'website')" />
  <meta property="og:site_name" content="{{ $siteTitle }}" />
  <meta property="og:title" content="{{ $resolvedOgTitle }}" />
  <meta property="og:description" content="{{ $resolvedOgDescription }}" />
  <meta property="og:image" content="{{ $resolvedOgImage }}" />
  @if(trim($__env->yieldContent('og-image-width')) !== '')
    <meta property="og:image:width" content="@yield('og-image-width')" />
  @endif
  @if(trim($__env->yieldContent('og-image-height')) !== '')
    <meta property="og:image:height" content="@yield('og-image-height')" />
  @endif
  <meta property="og:image:alt" content="{{ $resolvedOgTitle }}" />
  <meta property="og:url" content="{{ $canonicalUrl }}" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="{{ $resolvedOgTitle }}" />
  <meta name="twitter:description" content="{{ $resolvedOgDescription }}" />
  <meta name="twitter:image" content="{{ $resolvedOgImage }}" />
  @yield('structured-data')


  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="{{ route('manifest.web') }}">

  <!-- Title -->
  <title>{{ $resolvedTitle }}</title>
  <!-- Favicon Icon -->
  <link rel="shortcut icon" href="{{ asset('assets/admin/img/' . $websiteInfo->favicon) }}" type="image/x-icon">
  {{-- include styles --}}
  @if(trim($__env->yieldContent('react-only')) === 'true')
    <link rel="stylesheet" href="{{ asset('assets/front/css/fontawesome.5.9.0.min.css') }}">
  @else
    @includeIf('frontend.partials.styles')
  @endif
  @yield('custom-style')
  @vite(['resources/css/tica-front.css'])
  @if(trim($__env->yieldContent('react-only')) === 'true')
    @vite(['resources/js/tica-app.tsx'])
  @endif
</head>

<body class="tica-front-ui @yield('body-class')">
  <div class="page-wrapper">

    @if(trim($__env->yieldContent('react-only')) !== 'true')
      <div class="preloader" aria-hidden="true" style="background-image:url({{ asset('assets/admin/img/' . $websiteInfo->preloader) }})"></div>
      <div class="request-loader"><img src="{{ asset('assets/admin/img/loader.gif') }}" alt="loader"></div>
    @endif



    <!-- Header Part Start -->
    @if(trim($__env->yieldContent('minimal-layout')) !== 'true')
      @includeIf('frontend.partials.header.header-nav')
    @endif
    <!-- Header Part End -->

    @yield('hero-section')

    @yield('content')

    @if(trim($__env->yieldContent('react-only')) !== 'true')
      @includeIf('frontend.partials.popups')
    @endif


    @if(trim($__env->yieldContent('minimal-layout')) !== 'true')
      @includeIf('frontend.partials.footer.footer')
    @endif

  </div>
  <!--End pagewrapper-->

  {{-- modals --}}
  @yield('modals')
  {{-- include scripts --}}
  <script>
    "use strict";
    var rtl = {{ $currentLanguageInfo->direction }};
    window.ticaTrack = function (eventName, properties) {
      var sourceProperties = properties || {};
      var analyticsProperties = Object.assign({}, sourceProperties);
      if (eventName === 'search') {
        var query = String(sourceProperties.search_term || '').trim();
        analyticsProperties = {
          search_term: query,
          location: sourceProperties.location || '',
          category: sourceProperties.category || ''
        };
      }
      var payload = Object.assign({ event: eventName, occurred_at: new Date().toISOString() }, analyticsProperties);
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push(payload);
      window.dispatchEvent(new CustomEvent('tica:analytics', { detail: payload }));

      var browserEvents = ['search', 'view_event', 'select_ticket', 'begin_checkout', 'checkout_details'];
      if (browserEvents.indexOf(eventName) === -1) return;
      var token = document.querySelector('meta[name="csrf-token"]');
      if (!token || !window.fetch) return;
      window.fetch(@json(route('analytics.events.store')), {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token.getAttribute('content')
        },
        body: JSON.stringify({
          event: eventName,
          occurred_at: payload.occurred_at,
          properties: analyticsProperties
        })
      }).catch(function () {
        // Analytics is deliberately best-effort and must never interrupt a user journey.
      });
    };
    document.addEventListener('DOMContentLoaded', function () {
      var searchForm = document.getElementById('event-search');
      if (searchForm) {
        searchForm.addEventListener('submit', function () {
          var data = new FormData(searchForm);
          window.ticaTrack('search', {
            search_term: data.get('search-input') || '',
            location: data.get('location') || '',
            category: data.get('category') || ''
          });
        });
      }
    });
  </script>
  @if(trim($__env->yieldContent('react-only')) !== 'true')
    @includeIf('frontend.partials.scripts')
  @endif

  {{-- additional script --}}
  @yield('script')
  @yield('custom-script')

  @if(trim($__env->yieldContent('react-only')) !== 'true')
  <script>
    (function () {
      var released = false;
      var releasePreloader = function () {
        if (released) return;
        released = true;
        document.querySelectorAll('.preloader').forEach(function (preloader) {
          preloader.classList.add('preloader--hidden');
          window.setTimeout(function () {
            preloader.hidden = true;
          }, 350);
        });
      };

      if (document.readyState === 'complete') {
        releasePreloader();
      } else {
        window.addEventListener('load', releasePreloader, { once: true });
      }

      // The loading screen must never become a permanent single point of
      // failure when a third-party image or legacy plugin misses window.load.
      window.setTimeout(releasePreloader, 3000);
    }());
  </script>
  @endif

  {{-- Cookie alert dialog start --}}
  @if (!empty($cookieAlertInfo) && $cookieAlertInfo->cookie_alert_status == 1)
    <div class="cookie">
      @include('cookie-consent::index')
    </div>
  @endif
  {{-- Cookie alert dialog end --}}

</body>

</html>
