@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-directory.css') }}">
@endsection

@section('content')
  <div class="tica-directory-page audience-compose-page">
    <header class="directory-page-header">
      <div><a class="audience-back" href="{{ route('admin.audience.index',['segment'=>$audience]) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>{{ __('Audience') }}</a><h1>{{ __('Create email') }}</h1><p>{{ __('Write once, review the audience, then let TICA queue each delivery safely.') }}</p></div>
    </header>

    <form class="audience-compose" action="{{ route('admin.user_management.subscribers.send_email') }}" method="POST">
      @csrf
      <input type="hidden" name="audience" value="{{ $audience }}"><input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="ids" value="{{ $ids->implode(',') }}"><input type="hidden" name="event_id" value="{{ $eventId }}"><input type="hidden" name="q" value="{{ $search }}">
      <aside class="audience-compose-summary">
        <span class="audience-compose-icon"><i class="far fa-paper-plane" aria-hidden="true"></i></span>
        <div><span>{{ __('Recipients') }}</span><strong>{{ number_format($recipientCount) }}</strong><p>{{ $mode === 'selected' ? __('Selected ') : __('All matching ') }}{{ __(rtrim($audience,'s')) }}{{ $recipientCount === 1 ? '' : 's' }}</p></div>
        <a href="{{ route('admin.audience.index',['segment'=>$audience,'event_id'=>$eventId,'q'=>$search]) }}">{{ __('Change audience') }}</a>
      </aside>

      <section class="directory-surface audience-compose-surface">
        <div class="audience-compose-heading"><div><h2>{{ __('Email details') }}</h2><p>{{ $audience === 'subscribers' ? __('Marketing email to people who subscribed on TICA.') : __('Service message to people with a TICA relationship.') }}</p></div><span class="directory-status directory-status--ready">{{ __('Queued delivery') }}</span></div>
        <div class="audience-compose-fields">
          <label for="audience-subject">{{ __('Subject') }}</label>
          <input id="audience-subject" type="text" name="subject" maxlength="255" value="{{ old('subject') }}" placeholder="{{ __('What should recipients know?') }}" required>
          @error('subject')<p class="audience-field-error">{{ $message }}</p>@enderror
          <label for="audience-message">{{ __('Message') }}</label>
          <textarea id="audience-message" class="summernote" name="message" data-height="360" placeholder="{{ __('Write your message') }}">{{ old('message') }}</textarea>
          @error('message')<p class="audience-field-error">{{ $message }}</p>@enderror

          @if($audience !== 'subscribers')
            <label class="audience-purpose"><input type="checkbox" name="acknowledge" value="1" @checked(old('acknowledge')) required><span><strong>{{ __('This is a service or event message') }}</strong>{{ __('I will not use customer or attendee addresses for newsletter marketing without consent.') }}</span></label>
            @error('acknowledge')<p class="audience-field-error">{{ $message }}</p>@enderror
          @endif
        </div>
        <footer class="audience-compose-actions"><a class="directory-btn directory-btn--quiet" href="{{ route('admin.audience.index',['segment'=>$audience]) }}">{{ __('Cancel') }}</a><button class="directory-btn directory-btn--primary" type="submit" @disabled($recipientCount < 1)><i class="far fa-clock" aria-hidden="true"></i>{{ __('Queue email to :count',['count'=>number_format($recipientCount)]) }}</button></footer>
      </section>
    </form>

    <section class="directory-surface audience-campaigns">
      <div class="audience-compose-heading"><div><h2>{{ __('Recent campaigns') }}</h2><p>{{ __('Delivery progress across the latest queued messages.') }}</p></div></div>
      <div class="directory-table-wrap"><table class="directory-table"><thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Recipients') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Failed') }}</th><th>{{ __('Status') }}</th><th>{{ __('Queued') }}</th></tr></thead><tbody>
        @forelse($campaigns as $campaign)<tr><td><strong>{{ $campaign->subject }}</strong></td><td>{{ number_format($campaign->total_count) }}</td><td>{{ number_format($campaign->sent_count) }}</td><td>{{ number_format($campaign->failed_count) }}</td><td><span class="directory-status directory-status--{{ $campaign->status === 'completed' ? 'ready' : 'muted' }}">{{ __(str_replace('_',' ',ucfirst($campaign->status))) }}</span></td><td>{{ optional($campaign->created_at)->format('d M Y, H:i') }}</td></tr>
        @empty<tr><td colspan="6"><div class="directory-empty"><i class="far fa-paper-plane" aria-hidden="true"></i><strong>{{ __('No campaigns yet') }}</strong><span>{{ __('Your queued emails will appear here.') }}</span></div></td></tr>@endforelse
      </tbody></table></div>
    </section>
  </div>
@endsection
