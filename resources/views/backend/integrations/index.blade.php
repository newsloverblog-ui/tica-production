@extends('backend.layout')

@section('content')
  <div class="t-page-head">
    <div>
      <h1 class="page-title">{{ __('Integrations') }}</h1>
      <p>{{ __('Every outside service TICA needs, in one place: payments, payouts, email, SMS, push, sign-in and file storage.') }}</p>
    </div>
  </div>

  @if (!$storeReady)
    <div class="t-card in-note in-note--orange" role="status">
      <b>{{ __('One database update is waiting.') }}</b>
      {{ __('Payments, email and sign-in work now. SMS, push, storage and payout extras can be saved after running database/supabase/tica_database_update.sql in Supabase.') }}
    </div>
  @endif

  <div class="in-summary">
    <span><b>{{ $counts['ok'] ?? 0 }}</b> {{ __('connected') }}</span>
    <span><b>{{ $counts['attention'] ?? 0 }}</b> {{ __('need attention') }}</span>
    <span><b>{{ $counts['other'] ?? 0 }}</b> {{ __('off or not set up') }}</span>
  </div>

  @foreach ($groups as $groupKey => $groupName)
    @continue(empty($integrations[$groupKey]))
    <section class="in-group" aria-labelledby="in-{{ $groupKey }}">
      <h2 class="in-group__title" id="in-{{ $groupKey }}">{{ __($groupName) }}</h2>
      <div class="in-grid">
        @foreach ($integrations[$groupKey] as $i)
          <a class="t-card in-card" href="{{ route('admin.integrations.show', $i['key']) }}">
            <span class="in-card__top">
              <b class="in-card__name">{{ $i['name'] }}</b>
              <span class="t-badge{{ $i['status']['tone'] ? ' t-badge--' . $i['status']['tone'] : '' }}">{{ __($i['status']['label']) }}</span>
            </span>
            <span class="in-card__sum">{{ __($i['summary']) }}</span>
            <span class="in-card__foot">
              @if ($i['tested_at'])
                {{ $i['test_ok'] ? __('Last test passed') : __('Last test failed') }} · {{ \Carbon\Carbon::parse($i['tested_at'])->diffForHumans() }}
              @else
                {{ __('Not tested yet') }}
              @endif
              <span aria-hidden="true">→</span>
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endforeach
@endsection
