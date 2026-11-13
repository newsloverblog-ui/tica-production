@extends('organizer.layout')

@section('content')
  <div class="t-page-head">
    <div>
      <h1 class="page-title">{{ __('Support') }}</h1>
      <p>{{ __('Ask TICA for help and follow every conversation in one place.') }}</p>
    </div>
    <div class="lg-actions">
      <button class="t-btn t-btn--danger bulk-delete d-none" type="button" data-href="{{ route('organizer.support_tickets.bulk_delete') }}">{{ __('Delete selected') }}</button>
      <a class="t-btn t-btn--primary" href="{{ route('organizer.support_ticket.create') }}">{{ __('New request') }}</a>
    </div>
  </div>

  <form action="" method="GET" class="t-toolbar lg-filters" role="search">
    <label class="t-search"><span class="t-sr">{{ __('Search by request number') }}</span><input type="search" name="ticket_id" class="t-input" inputmode="numeric" placeholder="{{ __('Search by request number') }}" value="{{ request('ticket_id') }}"></label>
    <button class="t-btn t-btn--secondary" type="submit">{{ __('Search') }}</button>
    @if (filled(request('ticket_id')))<a class="t-btn t-btn--ghost" href="{{ route('organizer.support_tickets') }}">{{ __('Clear') }}</a>@endif
  </form>

  @if (session()->has('course_status_warning'))
    <p class="t-card lg-note" role="status">{{ session()->get('course_status_warning') }}</p>
  @endif

  @if (count($collection) == 0)
    <div class="t-card"><div class="t-empty">
      <h3>{{ filled(request('ticket_id')) ? __('No request with that number') : __('No support requests yet') }}</h3>
      <p>{{ __('Need help with an event, a payout or your account? We usually reply within one working day.') }}</p>
      <a class="t-btn t-btn--secondary" href="{{ route('organizer.support_ticket.create') }}">{{ __('New request') }}</a>
    </div></div>
  @else
    <div class="t-table-wrap">
      <table class="t-table t-table--cards">
        <thead><tr>
          <th scope="col" class="lg-check"><input type="checkbox" class="bulk-check" data-val="all" aria-label="{{ __('Select all') }}"></th>
          <th scope="col">{{ __('Request') }}</th><th scope="col">{{ __('Subject') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col"><span class="t-sr">{{ __('Actions') }}</span></th>
        </tr></thead>
        <tbody>
          @foreach ($collection as $item)
            <tr>
              <td class="lg-check"><input type="checkbox" class="bulk-check" data-val="{{ $item->id }}" aria-label="{{ __('Select request') }} #{{ $item->id }}"></td>
              <td data-label="{{ __('Request') }}">#{{ $item->id }}<span class="t-muted lg-sub">{{ optional($item->created_at)->format('j M Y') }}</span></td>
              <td data-label="{{ __('Subject') }}"><a class="lg-link" href="{{ route('organizer.support_tickets.message', $item->id) }}">{{ $item->subject }}</a></td>
              <td data-label="{{ __('Status') }}">
                @if ((int) $item->status === 1)<span class="t-badge t-badge--orange">{{ __('Waiting for TICA') }}</span>
                @elseif ((int) $item->status === 2)<span class="t-badge t-badge--blue">{{ __('Open') }}</span>
                @else<span class="t-badge">{{ __('Closed') }}</span>@endif
              </td>
              <td class="ev-actions">
                <details class="t-menu"><summary class="t-icon-btn" aria-label="{{ __('Actions for request') }} #{{ $item->id }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg></summary>
                  <div class="t-menu__list">
                    <a href="{{ route('organizer.support_tickets.message', $item->id) }}">{{ __('Open conversation') }}</a>
                    <form class="deleteForm" action="{{ route('organizer.support_tickets.delete', $item->id) }}" method="post">@csrf<button type="submit" class="deleteBtn is-danger">{{ __('Delete') }}</button></form>
                  </div>
                </details>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="t-pager lg-pager">{{ $collection->withQueryString()->links() }}</div>
  @endif
@endsection
