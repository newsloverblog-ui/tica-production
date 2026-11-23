@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-directory.css') }}">
@endsection

@section('content')
  <div class="tica-directory-page audience-page">
    <header class="directory-page-header">
      <div><h1>{{ __('Audience') }}</h1><p>{{ __('Find customers, ticket holders and newsletter subscribers, then message the right people.') }}</p></div>
      <div class="directory-header-actions">
        <a class="directory-btn directory-btn--quiet" href="{{ route('admin.user_management.push_notification.notification_for_visitors') }}"><i class="far fa-bell" aria-hidden="true"></i>{{ __('Push notification') }}</a>
        <a class="directory-btn directory-btn--primary" href="{{ route('admin.user_management.mail_for_subscribers', ['audience'=>$segment,'mode'=>'all','event_id'=>request('event_id'),'q'=>request('q')]) }}"><i class="far fa-paper-plane" aria-hidden="true"></i>{{ __('Message this group') }}</a>
      </div>
    </header>

    <div class="directory-stats audience-stats" aria-label="{{ __('Audience summary') }}">
      <a href="{{ route('admin.audience.index',['segment'=>'customers']) }}" @class(['is-active'=>$segment==='customers'])><span>{{ __('Customers') }}</span><strong>{{ number_format($summary['customers']) }}</strong><small>{{ __('Registered accounts') }}</small></a>
      <a href="{{ route('admin.audience.index',['segment'=>'attendees']) }}" @class(['is-active'=>$segment==='attendees'])><span>{{ __('Attendees') }}</span><strong>{{ number_format($summary['attendees']) }}</strong><small>{{ __('Issued tickets') }}</small></a>
      <a href="{{ route('admin.audience.index',['segment'=>'subscribers']) }}" @class(['is-active'=>$segment==='subscribers'])><span>{{ __('Subscribers') }}</span><strong>{{ number_format($summary['subscribers']) }}</strong><small>{{ __('Marketing opt-ins') }}</small></a>
    </div>

    <section class="directory-surface">
      <div class="directory-toolbar audience-toolbar"><form class="directory-filter" action="{{ route('admin.audience.index') }}" method="GET">
        <input type="hidden" name="segment" value="{{ $segment }}">
        <label class="directory-search"><i class="fas fa-search" aria-hidden="true"></i><input name="q" type="search" value="{{ request('q',request('email')) }}" placeholder="{{ $segment === 'subscribers' ? __('Search email') : __('Search name, email or ticket') }}"></label>
        @if($segment === 'attendees')<select name="event_id" aria-label="{{ __('Event group') }}"><option value="">{{ __('All events') }}</option>@foreach($events as $event)<option value="{{ $event->event_id }}" @selected((string)request('event_id') === (string)$event->event_id)>{{ $event->title }}</option>@endforeach</select>@endif
        <button class="directory-btn directory-btn--primary" type="submit">{{ __('Apply') }}</button>
        @if(request()->hasAny(['q','email','event_id']))<a class="directory-clear" href="{{ route('admin.audience.index',['segment'=>$segment]) }}">{{ __('Clear') }}</a>@endif
      </form></div>

      <div class="audience-guidance">
        @if($segment === 'subscribers')<i class="fas fa-check-circle" aria-hidden="true"></i><span><strong>{{ __('Marketing audience') }}</strong>{{ __('These people explicitly subscribed on the TICA website.') }}</span>
        @elseif($segment === 'attendees')<i class="fas fa-ticket-alt" aria-hidden="true"></i><span><strong>{{ __('Event audience') }}</strong>{{ __('Use this for ticket, venue, schedule and safety updates—not general marketing.') }}</span>
        @else<i class="fas fa-user-circle" aria-hidden="true"></i><span><strong>{{ __('Customer audience') }}</strong>{{ __('Use this for account and service information. Send marketing only to subscribers.') }}</span>@endif
      </div>

      <form id="audience-selection" action="{{ route('admin.user_management.mail_for_subscribers') }}" method="GET">
        <input type="hidden" name="audience" value="{{ $segment }}"><input type="hidden" name="mode" value="selected"><input type="hidden" name="ids" value="" data-selected-ids><input type="hidden" name="event_id" value="{{ request('event_id') }}"><input type="hidden" name="q" value="{{ request('q',request('email')) }}">
        <div class="directory-table-wrap"><table class="directory-table audience-table"><thead><tr><th><input type="checkbox" data-audience-all aria-label="{{ __('Select this page') }}"></th><th>{{ $segment === 'subscribers' ? __('Subscriber') : ($segment === 'attendees' ? __('Attendee') : __('Customer')) }}</th>@if($segment === 'attendees')<th>{{ __('Event') }}</th><th>{{ __('Ticket') }}</th>@elseif($segment === 'customers')<th>{{ __('Contact') }}</th><th>{{ __('Account') }}</th>@else<th>{{ __('Joined') }}</th>@endif<th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody>
          @forelse($records as $record)
            @php
              $name = $segment === 'customers' ? (trim($record->fname.' '.$record->lname) ?: $record->username) : ($segment === 'attendees' ? ($record->holder_name ?: __('Ticket holder')) : $record->email_id);
              $email = $segment === 'subscribers' ? $record->email_id : $record->email;
              $initial = mb_strtoupper(mb_substr($name ?: $email ?: 'A',0,1));
            @endphp
            <tr><td data-label=""><input type="checkbox" value="{{ $record->id }}" data-audience-row aria-label="{{ __('Select :name',['name'=>$name]) }}"></td>
              <td data-label="{{ __('Person') }}"><div class="directory-person"><span class="directory-avatar"><span>{{ $initial }}</span></span><span><strong>{{ $name }}</strong><small>{{ $email }}</small></span></div></td>
              @if($segment === 'attendees')<td data-label="{{ __('Event') }}"><strong>{{ $record->event_title ?: __('Event') }}</strong><small>{{ $record->checked_in_at ? __('Checked in') : __('Not checked in') }}</small></td><td data-label="{{ __('Ticket') }}"><strong>{{ $record->ticket_number ?: '—' }}</strong><small>{{ ucfirst($record->status ?: 'active') }}</small></td>
              @elseif($segment === 'customers')<td data-label="{{ __('Contact') }}"><strong>{{ $record->email }}</strong><small>{{ $record->phone ?: __('No phone added') }}</small></td><td data-label="{{ __('Account') }}"><span class="directory-status directory-status--{{ (int)$record->status === 1 ? 'ready' : 'muted' }}">{{ (int)$record->status === 1 ? __('Active') : __('Suspended') }}</span></td>
              @else<td data-label="{{ __('Joined') }}"><strong>{{ optional($record->created_at)->format('d M Y') ?: '—' }}</strong><small>{{ __('Newsletter') }}</small></td>@endif
              <td class="directory-table-action"><a class="directory-row-action" href="{{ route('admin.user_management.mail_for_subscribers',['audience'=>$segment,'mode'=>'selected','ids'=>$record->id]) }}">{{ __('Email') }}</a></td></tr>
          @empty<tr><td colspan="5"><div class="directory-empty"><i class="far fa-address-book" aria-hidden="true"></i><strong>{{ __('No people match these filters') }}</strong><span>{{ __('Try a broader search or choose another audience.') }}</span></div></td></tr>@endforelse
        </tbody></table></div>
        <div class="audience-selection-bar" data-selection-bar hidden><strong><span data-selection-count>0</span> {{ __('selected') }}</strong><button class="directory-btn directory-btn--primary" type="submit"><i class="far fa-envelope" aria-hidden="true"></i>{{ __('Email selected') }}</button><button type="button" class="directory-clear" data-selection-clear>{{ __('Clear') }}</button></div>
      </form>
      <div class="directory-footer"><span>{{ __('Showing :first–:last of :total',['first'=>$records->firstItem() ?: 0,'last'=>$records->lastItem() ?: 0,'total'=>$records->total()]) }}</span><div class="directory-pagination">{{ $records->links() }}</div></div>
    </section>
  </div>
@endsection

@section('script')
<script>document.addEventListener('DOMContentLoaded',function(){var rows=Array.from(document.querySelectorAll('[data-audience-row]')),all=document.querySelector('[data-audience-all]'),bar=document.querySelector('[data-selection-bar]'),count=document.querySelector('[data-selection-count]'),ids=document.querySelector('[data-selected-ids]');function sync(){var selected=rows.filter(function(row){return row.checked}).map(function(row){return row.value});ids.value=selected.join(',');count.textContent=selected.length;bar.hidden=selected.length===0;if(all){all.checked=rows.length>0&&selected.length===rows.length;all.indeterminate=selected.length>0&&selected.length<rows.length}}rows.forEach(function(row){row.addEventListener('change',sync)});if(all)all.addEventListener('change',function(){rows.forEach(function(row){row.checked=all.checked});sync()});document.querySelector('[data-selection-clear]')?.addEventListener('click',function(){rows.forEach(function(row){row.checked=false});sync()});document.getElementById('audience-selection')?.addEventListener('submit',function(event){if(!ids.value)event.preventDefault()})});</script>
@endsection
