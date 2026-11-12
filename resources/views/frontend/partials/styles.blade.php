<!-- FlatIcon Font -->
<link rel="stylesheet" href="{{ asset('assets/front/css/flaticon.css') }}">
<!-- Font Awesome -->
<link rel="stylesheet" href="{{ asset('assets/front/css/fontawesome.5.9.0.min.css') }}">
<!-- Bootstrap css -->
<link rel="stylesheet" href="{{ asset('assets/front/css/bootstrap.4.5.3.min.css') }}">
<!-- Magnific Popup -->
<link rel="stylesheet" href="{{ asset('assets/front/css/magnific-popup.min.css') }}">
<!-- Slick Slider -->
<link rel="stylesheet" href="{{ asset('assets/front/css/slick.css') }}">
<!-- jQuery UI CSS -->
<link rel="stylesheet" href="{{ asset('assets/front/css/jquery-ui.min.css') }}">
<!-- Padding Margin -->
<link rel="stylesheet" href="{{ asset('assets/front/css/spacing.min.css') }}">
<!-- Menu css -->
<link rel="stylesheet" href="{{ asset('assets/front/css/menu.css') }}">
<!-- datatables css -->
<link rel="stylesheet" href="{{ asset('assets/front/css/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front/css/dataTables.bootstrap4.css') }}">
<!-- dashboard css -->
<link rel="stylesheet" href="{{ asset('assets/front/css/dashboard.css') }}">
<!-- Menu css -->
<link rel="stylesheet" href="{{ asset('assets/front/css/menu.css') }}">
<!-- Main css -->
<link rel="stylesheet" href="{{ asset('assets/front/css/style.css') }}">
<!-- Responsive css -->
<link rel="stylesheet" href="{{ asset('assets/front/css/responsive.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front/css/daterangepicker.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/front/css/toastr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front/css/organizer.css') }}">
@if ($currentLanguageInfo->direction == 1)
  {{-- right-to-left css --}}
  <link rel="stylesheet" href="{{ asset('assets/front/css/rtl-style.css') }}">

  {{-- right-to-left-responsive css --}}
  <link rel="stylesheet" href="{{ asset('assets/front/css/rtl-responsive.css') }}">
@endif
<style>
  :root {
    scroll-behavior: auto;
    --tica-blue: #0011FF;
    --tica-electric: #00A8E8;
    --tica-orange: #FF8C42;
    --tica-navy: #003366;
    --tica-charcoal: #333333;
    --tica-gray: #F5F5F5;
    --base-color: var(--tica-charcoal);
    --heading-color: var(--tica-navy);
    --primary-color: var(--tica-blue);
    --light-color: var(--tica-gray);
    --base-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    --heading-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  }

  .overlay:before {
    position: absolute;
    content: '';
    height: 100%;
    width: 100%;
    left: 0;
    top: 0;
    z-index: -1;
    opacity: {{ $basicInfo->breadcrumb_overlay_opacity }};
    background: #{{ $basicInfo->breadcrumb_overlay_color }};
  }
</style>
