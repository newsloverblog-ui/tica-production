@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-directory.css') }}">
@endsection

@section('content')
  <div class="tica-directory-page audience-compose-page">
    <header class="directory-page-header">
      <div><a class="audience-back" href="{{ route('admin.audience.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>{{ __('Subscribers') }}</a><h1>{{ __('Create newsletter email') }}</h1><p>{{ __('Write once, review the recipients, then let TICA queue each delivery safely.') }}</p></div>
    </header>

    <form class="audience-compose" action="{{ route('admin.user_management.subscribers.send_email') }}" method="POST">
      @csrf
      <input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="ids" value="{{ $ids->implode(',') }}"><input type="hidden" name="q" value="{{ $search }}">
      <aside class="audience-compose-summary">
        <span class="audience-compose-icon"><i class="far fa-paper-plane" aria-hidden="true"></i></span>
        <div><span>{{ __('Newsletter recipients') }}</span><strong>{{ number_format($recipientCount) }}</strong><p>{{ $mode === 'selected' ? __('Selected subscribers') : __('All matching subscribers') }}</p></div>
        <a href="{{ route('admin.audience.index', ['q' => $search]) }}">{{ __('Change recipients') }}</a>
      </aside>

      <section class="directory-surface audience-compose-surface">
        <div class="audience-compose-heading"><div><h2>{{ __('Email details') }}</h2><p>{{ __('Marketing email to people who explicitly subscribed through the TICA newsletter form.') }}</p></div><span class="directory-status directory-status--ready">{{ __('Consent verified') }}</span></div>
        <div class="audience-compose-fields">
          <label for="audience-subject">{{ __('Subject') }}</label>
          <input id="audience-subject" type="text" name="subject" maxlength="255" value="{{ old('subject') }}" placeholder="{{ __('What should subscribers know?') }}" required>
          @error('subject')<p class="audience-field-error">{{ $message }}</p>@enderror
          <label for="audience-message">{{ __('Message') }}</label>
          <textarea id="audience-message" class="summernote" name="message" data-height="360" placeholder="{{ __('Write your newsletter') }}">{{ old('message') }}</textarea>
          @error('message')<p class="audience-field-error">{{ $message }}</p>@enderror
        </div>
        <footer class="audience-compose-actions"><a class="directory-btn directory-btn--quiet" href="{{ route('admin.audience.index') }}">{{ __('Cancel') }}</a><button class="directory-btn directory-btn--primary" type="submit" @disabled($recipientCount < 1)><i class="far fa-clock" aria-hidden="true"></i>{{ __('Queue email to :count', ['count' => number_format($recipientCount)]) }}</button></footer>
      </section>
    </form>

    <section class="directory-surface audience-campaigns">
      <div class="audience-compose-heading"><div><h2>{{ __('Recent newsletter campaigns') }}</h2><p>{{ __('Delivery progress across the latest queued subscriber emails.') }}</p></div></div>
      <div class="directory-table-wrap"><table class="directory-table"><thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Recipients') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Failed') }}</th><th>{{ __('Status') }}</th><th>{{ __('Queued') }}</th></tr></thead><tbody>
        @forelse($campaigns as $campaign)<tr><td><strong>{{ $campaign->subject }}</strong></td><td>{{ number_format($campaign->total_count) }}</td><td>{{ number_format($campaign->sent_count) }}</td><td>{{ number_format($campaign->failed_count) }}</td><td><span class="directory-status directory-status--{{ $campaign->status === 'completed' ? 'ready' : 'muted' }}">{{ __(str_replace('_', ' ', ucfirst($campaign->status))) }}</span></td><td>{{ optional($campaign->created_at)->format('d M Y, H:i') }}</td></tr>
        @empty<tr><td colspan="6"><div class="directory-empty"><i class="far fa-paper-plane" aria-hidden="true"></i><strong>{{ __('No campaigns yet') }}</strong><span>{{ __('Your queued newsletter emails will appear here.') }}</span></div></td></tr>@endforelse
      </tbody></table></div>
    </section>
  </div>
@endsection
