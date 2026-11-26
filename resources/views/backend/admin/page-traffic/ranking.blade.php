<section class="cd-card" aria-labelledby="{{ $id }}">
  <div class="cd-card-h">
    <h2 id="{{ $id }}">{{ $heading }}</h2>
    @if ($rows)<span class="pt-rank-unit">{{ __('Page views') }}</span>@endif
  </div>
  @if ($rows)
    <ol class="pt-ranked">
      @foreach ($rows as $row)
        <li>
          @if ($row['url'])
            <a href="{{ $row['url'] }}">{{ $row['label'] }}</a>
          @else
            <span>{{ $row['label'] }}</span>
          @endif
          <strong>{{ number_format($row['views']) }}</strong>
          <span class="pt-ranked-bar" aria-hidden="true">
            <span style="width: {{ 100 * $row['views'] / $rows[0]['views'] }}%"></span>
          </span>
        </li>
      @endforeach
    </ol>
  @else
    <p class="cd-empty">{{ __('No details for this date range.') }}</p>
  @endif
</section>
