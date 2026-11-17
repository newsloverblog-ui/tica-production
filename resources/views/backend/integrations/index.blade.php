@extends('backend.layout')

@section('content')
  <div class="t-page-head">
    <div>
      <h1 class="page-title">{{ __('Integrations') }}</h1>
      <p>{{ __('Connect payments, email, sign-in and notifications without exposing provider credentials.') }}</p>
    </div>
  </div>

  @if (!$storeReady)
    <div class="t-card in-note in-note--orange" role="status">
      <b>{{ __('One database update is waiting.') }}</b>
      {{ __('Payments, email and sign-in work now. SMS, push, storage and payout extras can be saved after running database/supabase/tica_database_update.sql in Supabase.') }}
    </div>
  @endif

  <div class="in-summary" aria-label="Integration health summary">
    <span><b>{{ $counts['ok'] ?? 0 }}</b> {{ __('connected') }}</span>
    <span><b>{{ $counts['attention'] ?? 0 }}</b> {{ __('need attention') }}</span>
    <span><b>{{ $counts['other'] ?? 0 }}</b> {{ __('off or not configured') }}</span>
  </div>

  <section class="in-core" aria-labelledby="in-core-title">
    <div class="in-section-head">
      <div><h2 id="in-core-title">{{ __('Core connections') }}</h2><p>{{ __('The services used directly in checkout, account access and customer communication.') }}</p></div>
    </div>
    <div class="in-connections">
      @foreach ($coreIntegrations as $i)
        <a class="t-card in-connection" href="{{ route('admin.integrations.show', $i['key']) }}">
          @include('backend.integrations._icon', ['icon' => $i['icon']])
          <span class="in-connection__body">
            <span class="in-connection__title"><b>{{ $i['name'] }}</b><small>{{ $i['mode'] }}</small></span>
            <span class="in-connection__summary">{{ __($i['summary']) }}</span>
          </span>
          <span class="in-connection__health">
            <span class="t-badge{{ $i['status']['tone'] ? ' t-badge--' . $i['status']['tone'] : '' }}">{{ __($i['status']['label']) }}</span>
            <small>{{ $i['enabled'] ? __('Enabled') : __('Disabled') }}</small>
          </span>
          <span class="in-connection__test">
            @if ($i['tested_at'])
              {{ $i['test_ok'] ? __('Test passed') : __('Test failed') }}<small>{{ \Carbon\Carbon::parse($i['tested_at'])->diffForHumans() }}</small>
            @else
              {{ __('Not tested') }}<small>{{ __('Open to configure') }}</small>
            @endif
          </span>
          <span class="in-connection__open" aria-hidden="true">›</span>
        </a>
      @endforeach
    </div>
  </section>

  @foreach ($groups as $groupKey => $groupName)
    @continue(empty($integrations[$groupKey]))
    <section class="in-group" aria-labelledby="in-{{ $groupKey }}">
      <h2 class="in-group__title" id="in-{{ $groupKey }}">{{ __($groupName) }}</h2>
      <div class="in-grid">
        @foreach ($integrations[$groupKey] as $i)
          <a class="t-card in-card" href="{{ route('admin.integrations.show', $i['key']) }}">
            <span class="in-card__top">
              <span class="in-card__identity">@include('backend.integrations._icon', ['icon' => $i['icon']])<b class="in-card__name">{{ $i['name'] }}</b></span>
              <span class="t-badge{{ $i['status']['tone'] ? ' t-badge--' . $i['status']['tone'] : '' }}">{{ __($i['status']['label']) }}</span>
            </span>
            <span class="in-card__sum">{{ __($i['summary']) }}</span>
            <span class="in-card__foot">
              @if ($i['tested_at'])
                {{ $i['test_ok'] ? __('Last test passed') : __('Last test failed') }} · {{ \Carbon\Carbon::parse($i['tested_at'])->diffForHumans() }}
              @else
                {{ __('Not tested yet') }}
              @endif
              <span>{{ $i['mode'] }}</span>
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endforeach
@endsection
