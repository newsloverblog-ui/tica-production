@extends('organizer.layout')

@section('page-title', __('Customer messages'))

@section('content')
<div class="t-page-head">
  <div><h1 class="page-title">{{ __('Customer messages') }}</h1><p>{{ __('Questions from people who booked your events.') }}</p></div>
</div>

<form class="t-toolbar" method="GET" role="search">
  <label class="t-search"><span class="t-sr">{{ __('Search messages') }}</span><input class="t-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search subject or email') }}"></label>
  <button class="t-btn t-btn--secondary" type="submit">{{ __('Search') }}</button>
</form>

@if($collection->isEmpty())
  <section class="t-card"><div class="t-empty"><h2>{{ __('No customer messages') }}</h2><p>{{ __('Messages linked to your events will appear here.') }}</p></div></section>
@else
  <div class="t-table-wrap">
    <table class="t-table t-table--cards">
      <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Event') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
      <tbody>
      @foreach($collection as $ticket)
        <tr>
          <td data-label="{{ __('Customer') }}"><b>{{ trim(($ticket->customer?->fname ?? '').' '.($ticket->customer?->lname ?? '')) ?: $ticket->email }}</b><span class="t-muted lg-sub">{{ $ticket->email }}</span></td>
          <td data-label="{{ __('Event') }}">{{ $ticket->event?->title ?: __('Event') }}</td>
          <td data-label="{{ __('Subject') }}">{{ $ticket->subject }}<span class="t-muted lg-sub">{{ $ticket->last_message ? \Carbon\Carbon::parse($ticket->last_message)->diffForHumans() : '' }}</span></td>
          <td data-label="{{ __('Status') }}"><span class="t-badge {{ (int)$ticket->status === 3 ? '' : 't-badge--blue' }}">{{ (int)$ticket->status === 3 ? __('Closed') : __('Open') }}</span></td>
          <td><a class="t-btn t-btn--secondary t-btn--sm" href="{{ route('organizer.customer_messages.show', $ticket) }}">{{ __('Open') }}</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  <div class="t-pager">{{ $collection->withQueryString()->links() }}</div>
@endif
@endsection
