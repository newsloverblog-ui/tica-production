@extends('organizer.layout')

@section('style')
  <link rel="manifest" crossorigin="use-credentials" href="{{ route('manifest.scanner') }}" />
@endsection

@section('content')
  @php($canManageStaff = \App\Support\TeamPermissions::isOwner())
  <div class="tsc tsc-setup">
    <div class="tsc-head">
      <div class="tsc-head__copy">
        <p class="tsc-kicker">{{ __('Gate') }}</p>
        <h1>{{ $canManageStaff ? __('Gate staff and devices') : __('Gate devices') }}</h1>
        <p>{{ $canManageStaff ? __('Set up trusted devices and individual staff access before doors open.') : __('Set up trusted devices before doors open.') }}</p>
      </div>
      <div class="tsc-actions">
        <a class="btn btn-secondary" href="{{ route('organizer.check_in_ledger_exports.index') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/></svg>
          {{ __('Check-in ledger') }}
        </a>
        <a class="btn btn-primary" href="{{ url('/checkin') }}">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2M20 14v3M14 20h6M17 17h3"/></svg>
          {{ __('Open gate check-in') }}
        </a>
      </div>
    </div>

    <nav class="tsc-tabs" aria-label="{{ __('Gate setup sections') }}">
      <a href="#scanner-device"><span>1</span>{{ __('Devices') }}</a>
      @if($canManageStaff)
        <a href="#scanner-staff"><span>2</span>{{ __('Staff access') }}</a>
      @endif
      <a href="#scanner-offline" id="scanner-offline-tab" class="d-none">{{ config('tickets.offline_enabled') ? __('Offline readiness') : __('Live attendance') }}</a>
      <a href="#scanner-conflicts-card">{{ __('Conflicts') }}</a>
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
