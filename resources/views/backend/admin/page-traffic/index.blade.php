@extends('backend.layout')

@section('content')
  @php
    $length = count($traffic['days']);
    $formatDate = fn ($date) => $date->translatedFormat('j M Y');
    $enteredFrom = old('from');
    $enteredTo = old('to');
  @endphp
  <div class="cd pt">
    <header class="cd-top">
      <div>
        <nav class="cd-crumb" aria-label="{{ __('Breadcrumb') }}">
          <a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a>
          <span aria-hidden="true">/</span>
          <span aria-current="page">{{ __('Page traffic') }}</span>
        </nav>
        <h1>{{ __('Page traffic') }}</h1>
        <p>{{ __('See how people find and browse public pages on TICA.') }}</p>
      </div>
      <a class="cd-btn" href="{{ route('admin.dashboard') }}#cd-traffic-h">
        <i class="fal fa-signal" aria-hidden="true"></i>{{ __('Live activity') }}
      </a>
    </header>

    <section class="cd-card pt-filter" aria-label="{{ __('Date range') }}">
      <nav class="pt-presets" aria-label="{{ __('Quick date ranges') }}">
        @foreach ([7, 30, 90] as $days)
          @php($selected = $to->equalTo($today) && $length === $days)
          <a class="cd-btn sm" href="{{ route('admin.analytics.page_traffic', ['from' => $today->subDays($days - 1)->toDateString(), 'to' => $today->toDateString()]) }}"
            @if ($selected) aria-current="true" @endif>{{ __('Last :days days', ['days' => $days]) }}</a>
        @endforeach
      </nav>
      <form class="pt-dates" method="get" action="{{ route('admin.analytics.page_traffic') }}">
        <div>
          <label for="pt-from">{{ __('From') }}</label>
          <input class="form-control" id="pt-from" name="from" type="date" value="{{ is_string($enteredFrom) ? $enteredFrom : $from->toDateString() }}" max="{{ $today->toDateString() }}" required>
        </div>
        <div>
          <label for="pt-to">{{ __('To') }}</label>
          <input class="form-control" id="pt-to" name="to" type="date" value="{{ is_string($enteredTo) ? $enteredTo : $to->toDateString() }}" max="{{ $today->toDateString() }}" required>
        </div>
        <button type="submit" class="cd-btn primary">{{ __('Apply range') }}</button>
      </form>
      @if ($errors->hasAny(['from', 'to']))
        <p class="pt-date-error" role="alert">{{ $errors->first('from') ?: $errors->first('to') }}</p>
      @endif
    </section>

    <section class="cd-card" aria-labelledby="pt-trend-h">
      <div class="cd-card-h">
        <div>
          <h2 id="pt-trend-h">{{ __('Traffic over time') }}</h2>
          <p class="cd-traffic-caption">
            <time datetime="{{ $from->toDateString() }}">{{ $formatDate($from) }}</time>
            <span aria-hidden="true">–</span><span class="sr-only">{{ __('to') }}</span>
            <time datetime="{{ $to->toDateString() }}">{{ $formatDate($to) }}</time>
          </p>
        </div>
        @if ($traffic['views'])
          <div class="pt-chart-options" data-traffic-options hidden>
            <button class="cd-btn sm" type="button" data-traffic-chart="bar" aria-pressed="true">
              <i class="fal fa-chart-bar" aria-hidden="true"></i>{{ __('Bars') }}
            </button>
            <button class="cd-btn sm" type="button" data-traffic-chart="line" aria-pressed="false">
              <i class="fal fa-chart-line" aria-hidden="true"></i>{{ __('Lines') }}
            </button>
          </div>
        @endif
      </div>

      <dl class="pt-metrics">
        <div>
          <dt><i class="fal fa-eye" aria-hidden="true"></i>{{ __('Page views') }}</dt>
          <dd>{{ number_format($traffic['views']) }}</dd>
        </div>
        <div>
          <dt><i class="fal fa-users" aria-hidden="true"></i>{{ __('Sessions') }}</dt>
          <dd>{{ number_format($traffic['sessions']) }}</dd>
        </div>
        <div>
          <dt><i class="fal fa-layer-group" aria-hidden="true"></i>{{ __('Views per session') }}</dt>
          <dd>{{ $traffic['viewsPerSession'] === null ? '—' : number_format($traffic['viewsPerSession'], 1) }}</dd>
        </div>
      </dl>

      @if ($traffic['views'])
        <div class="pt-chart-wrap" data-traffic-plot hidden>
          <ul class="pt-legend" aria-label="{{ __('Chart series') }}">
            <li><span class="pt-legend-views" aria-hidden="true"></span>{{ __('Page views') }}</li>
            <li><span class="pt-legend-sessions" aria-hidden="true"></span>{{ __('Sessions') }}</li>
          </ul>
          <div class="pt-chart">
            <canvas id="pt-chart" role="img" aria-label="{{ __('Daily page views and sessions. Use the daily values table for specific counts.') }}"></canvas>
          </div>
        </div>
        <details class="cd-series">
          <summary>{{ __('Show daily values') }}</summary>
          <div class="cd-scroll" tabindex="0" role="region" aria-label="{{ __('Daily traffic table') }}">
            <table>
              <thead>
                <tr><th scope="col">{{ __('Date') }}</th><th scope="col" class="cd-num">{{ __('Page views') }}</th><th scope="col" class="cd-num">{{ __('Sessions') }}</th></tr>
              </thead>
              <tbody>
                @foreach ($traffic['days'] as $day)
                  <tr>
                    <th scope="row"><time datetime="{{ $day['date'] }}">{{ $day['label'] }}</time></th>
                    <td class="cd-num">{{ number_format($day['views']) }}</td>
                    <td class="cd-num">{{ number_format($day['sessions']) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </details>
      @else
        <p class="cd-empty">{{ __('No page views were recorded in this date range.') }}</p>
      @endif
    </section>

    <div class="pt-rankings">
      @include('backend.admin.page-traffic.ranking', ['heading' => __('Most viewed pages'), 'id' => 'pt-pages', 'rows' => $traffic['pages']])
      @include('backend.admin.page-traffic.ranking', ['heading' => __('Referrers'), 'id' => 'pt-referrers', 'rows' => $traffic['referrers']])
    </div>
    <p class="pt-note">{{ __('Counts include public visits with analytics consent. Date ranges use :zone. Page and referrer details are available for visits recorded after detail tracking began.', ['zone' => config('app.timezone')]) }}</p>
  </div>
@endsection

@section('script')
  @if ($traffic['views'])
    <script type="application/json" id="pt-chart-data">@json($traffic['days'])</script>
    <script src="{{ asset('assets/admin/js/chart.min.js') }}" defer></script>
    <script src="{{ asset('assets/tica-ui/js/admin-page-traffic.js') }}?v={{ tica_asset_version('assets/tica-ui/js/admin-page-traffic.js') }}" defer></script>
  @endif
@endsection
