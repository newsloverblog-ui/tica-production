@extends('organizer.layout')

@section('style')
  <link rel="manifest" crossorigin="use-credentials" href="{{ route('manifest.scanner') }}" />
@endsection

@section('content')
  <div class="tsc tsc-setup">
    <div class="tsc-head">
      <div>
        <p class="tsc-kicker">{{ __('Gate') }}</p>
        <h1>{{ __('Gate staff and devices') }}</h1>
        <p>{{ __('Approve the phones and tablets used at your gates, give your gate team their own logins, and prepare events for offline check-in. Scanning tickets happens in Gate check-in.') }}</p>
      </div>
      <div class="tsc-actions">
        <a class="btn btn-primary" href="{{ url('/checkin') }}">{{ __('Open gate check-in') }}</a>
        <a class="btn btn-secondary" href="{{ route('organizer.check_in_ledger_exports.index') }}">{{ __('Check-in ledger') }}</a>
      </div>
    </div>

    <nav class="tsc-tabs" aria-label="{{ __('Scanner sections') }}">
      <a href="#scanner-device">{{ __('This device') }}</a>
      <a href="#scanner-staff">{{ __('Gate staff') }}</a>
      <a href="#scanner-offline">{{ __('Offline and live counts') }}</a>
      <a href="#scanner-conflicts-card">{{ __('Gate conflicts') }}</a>
    </nav>

    @include('organizer.pwa.partials.panels')
  </div>
@endsection

@section('script')
  @include('organizer.pwa.partials.vars')
  {{-- Setup only: tickets are scanned in Gate check-in (/checkin), not here. --}}
  <script>window.TICA_SCANNER_SETUP_ONLY = true;</script>
  <script src="{{ asset('assets/pwa_scanner/html5-qrcode.min.js') }}"></script>
  <script src="{{ asset('assets/pwa_scanner/pwa.js') }}" defer></script>
  <script src="{{ asset('assets/pwa_scanner/script.js') }}"></script>
@endsection
