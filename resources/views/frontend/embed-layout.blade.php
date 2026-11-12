<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('pageHeading') | Tica</title>
  <link rel="stylesheet" href="{{ asset('assets/front/css/fontawesome.5.9.0.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/front/css/bootstrap.4.5.3.min.css') }}">
  @yield('custom-style')
</head>
<body class="@yield('body-class')">
  @yield('content')
  @yield('custom-script')
  <script>
    (function () {
      function announceHeight() {
        window.parent.postMessage({ type: 'tica-registration-resize', height: document.documentElement.scrollHeight }, '*');
      }
      window.addEventListener('load', announceHeight);
      window.addEventListener('resize', announceHeight);
      if (window.ResizeObserver) new ResizeObserver(announceHeight).observe(document.body);
    })();
  </script>
</body>
</html>
