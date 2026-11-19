{{-- fontawesome css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/all.min.css') }}">

{{-- fontawesome icon picker css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/fontawesome-iconpicker.min.css') }}">

{{-- bootstrap css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap.min.css') }}">

{{-- bootstrap tags-input css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap-tagsinput.css') }}">

{{-- jQuery-ui css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/jquery-ui.min.css') }}">

{{-- jQuery-timepicker css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/jquery.timepicker.min.css') }}">

{{-- bootstrap-datepicker css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/bootstrap-datepicker.css') }}">

{{-- select2 css --}}
<link rel="stylesheet" href="{{asset('assets/admin/css/select2.min.css')}}">

{{-- dropzone css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/dropzone.min.css') }}">

{{-- monokai css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/monokai-sublime.css') }}">

{{-- atlantis css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/atlantis.css') }}">

{{-- admin-main css --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/admin-main.css') }}">

{{-- Tica control center design system --}}
<link rel="stylesheet" href="{{ asset('assets/admin/css/tica-control-center.css') }}">

{{-- TICA design: blends these pages with the organiser workspace --}}
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/organizer-blend.css') }}?v={{ tica_asset_version('assets/tica-ui/css/organizer-blend.css') }}">
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/ds.css') }}?v={{ tica_asset_version('assets/tica-ui/css/ds.css') }}">
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/human-ui.css') }}?v={{ tica_asset_version('assets/tica-ui/css/human-ui.css') }}">
{{-- The organiser sidebar and top bar: always last, so nothing above can restyle them. --}}
<link rel="preload" href="{{ asset('assets/tica-ui/fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/shell.css') }}?v={{ tica_asset_version('assets/tica-ui/css/shell.css') }}">
