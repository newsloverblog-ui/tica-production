<section class="cd-card cd-traffic" aria-labelledby="cd-traffic-h"
  data-traffic-url="{{ route('admin.analytics.site_traffic', [], false) }}"
  data-traffic-error="{{ __('Live data is temporarily unavailable. Retrying automatically.') }}"
  data-traffic-auth="{{ __('Your session has ended. Reload the dashboard to sign in again.') }}">
  <div class="cd-card-h">
    <div>
      <h2 id="cd-traffic-h">{{ __('Live site traffic') }}</h2>
      <p class="cd-traffic-caption">{{ __('Public pages viewed by visitors who allow analytics. Refreshes every 15 seconds.') }}</p>
    </div>
    <a class="cd-btn sm" href="{{ route('admin.analytics.page_traffic') }}">{{ __('View page traffic') }} <i class="fal fa-arrow-right" aria-hidden="true"></i></a>
  </div>
  <p class="cd-traffic-status" data-traffic-status role="status">{{ __('Loading traffic…') }}</p>
  <noscript><p class="cd-traffic-status">{{ __('Enable JavaScript to see live traffic.') }}</p></noscript>
  <dl class="cd-today" aria-label="{{ __('Traffic counts') }}">
    <div><dt>{{ __('Active sessions · 5 min') }}</dt><dd data-traffic-stat="active_sessions">—</dd></div>
    <div><dt>{{ __('Page views · 30 min') }}</dt><dd data-traffic-stat="views_30_minutes">—</dd></div>
    <div><dt>{{ __('Page views · 24 hours') }}</dt><dd data-traffic-stat="views_24_hours">—</dd></div>
  </dl>
  <figure class="cd-traffic-history" data-traffic-history hidden>
    <figcaption id="cd-traffic-chart-caption">{{ __('Page views by 5-minute interval') }}</figcaption>
    <ol class="cd-traffic-bars" aria-labelledby="cd-traffic-chart-caption"></ol>
    <p class="cd-traffic-caption">{{ __('Updated') }} <time data-traffic-updated></time></p>
  </figure>
</section>
