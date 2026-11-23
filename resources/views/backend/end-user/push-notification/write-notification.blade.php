@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-directory.css') }}">
@endsection

@section('content')
<div class="tica-directory-page audience-compose-page">
  <header class="directory-page-header"><div><a class="audience-back" href="{{ route('admin.audience.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>{{ __('Audience') }}</a><h1>{{ __('Create push notification') }}</h1><p>{{ __('Reach browsers that have explicitly allowed TICA notifications.') }}</p></div><a class="directory-btn directory-btn--quiet" href="{{ route('admin.user_management.push_notification.settings') }}"><i class="fas fa-cog" aria-hidden="true"></i>{{ __('Push settings') }}</a></header>

  <form class="audience-compose" action="{{ route('admin.user_management.push_notification.send_notification') }}" method="POST">@csrf
    <aside class="audience-compose-summary"><span class="audience-compose-icon"><i class="far fa-bell" aria-hidden="true"></i></span><div><span>{{ __('Subscribed browsers') }}</span><strong>{{ number_format($recipientCount) }}</strong><p>{{ __('Only browsers that granted permission can receive this.') }}</p></div></aside>
    <section class="directory-surface audience-compose-surface">
      <div class="audience-compose-heading"><div><h2>{{ __('Notification details') }}</h2><p>{{ __('Keep it short and link people to one useful next step.') }}</p></div><span class="directory-status directory-status--{{ $vapidConfigured ? 'ready' : 'failed' }}">{{ $vapidConfigured ? __('Push ready') : __('Setup required') }}</span></div>
      <div class="audience-compose-fields push-compose-fields">
        <label for="push-title">{{ __('Title') }}</label><input id="push-title" type="text" name="title" maxlength="255" value="{{ old('title') }}" placeholder="{{ __('What should people notice?') }}" required>@error('title')<p class="audience-field-error">{{ $message }}</p>@enderror
        <label for="push-message">{{ __('Message') }}</label><textarea id="push-message" name="message" rows="5" maxlength="1000" placeholder="{{ __('Add the essential detail') }}">{{ old('message') }}</textarea>@error('message')<p class="audience-field-error">{{ $message }}</p>@enderror
        <div class="push-action-grid"><div><label for="push-button">{{ __('Button label') }}</label><input id="push-button" type="text" name="button_name" maxlength="80" value="{{ old('button_name','View details') }}" required></div><div><label for="push-url">{{ __('Destination URL') }}</label><input id="push-url" type="url" name="button_url" maxlength="2048" value="{{ old('button_url',url('/discover')) }}" required></div></div>
        @error('button_name')<p class="audience-field-error">{{ $message }}</p>@enderror @error('button_url')<p class="audience-field-error">{{ $message }}</p>@enderror
        <p class="push-scope-note"><i class="fas fa-info-circle" aria-hidden="true"></i>{{ __('At the moment, push notifications go to every opted-in browser. Customer-level targeting will only appear after a browser subscription is linked to a signed-in account.') }}</p>
      </div>
      <footer class="audience-compose-actions"><a class="directory-btn directory-btn--quiet" href="{{ route('admin.audience.index') }}">{{ __('Cancel') }}</a><button class="directory-btn directory-btn--primary" type="submit" @disabled(!$vapidConfigured || $recipientCount < 1)><i class="far fa-clock" aria-hidden="true"></i>{{ __('Queue notification') }}</button></footer>
    </section>
  </form>

  <section class="directory-surface audience-campaigns"><div class="audience-compose-heading"><div><h2>{{ __('Recent push campaigns') }}</h2><p>{{ __('Delivery progress for opted-in browsers.') }}</p></div></div><div class="directory-table-wrap"><table class="directory-table"><thead><tr><th>{{ __('Queued') }}</th><th>{{ __('Status') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Failed') }}</th></tr></thead><tbody>
    @forelse($campaigns as $campaign)<tr><td>{{ optional($campaign->created_at)->format('d M Y, H:i') }}</td><td><span class="directory-status directory-status--{{ $campaign->status === 'completed' ? 'ready' : 'muted' }}">{{ __(str_replace('_',' ',$campaign->status)) }}</span></td><td>{{ number_format($campaign->total_count) }}</td><td>{{ number_format($campaign->sent_count) }}</td><td>{{ number_format($campaign->failed_count) }}</td></tr>
    @empty<tr><td colspan="5"><div class="directory-empty"><i class="far fa-bell" aria-hidden="true"></i><strong>{{ __('No push campaigns yet') }}</strong><span>{{ __('Queued notifications will appear here.') }}</span></div></td></tr>@endforelse
  </tbody></table></div></section>
</div>
@endsection
