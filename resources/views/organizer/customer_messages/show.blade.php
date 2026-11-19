@extends('organizer.layout')

@section('page-title', __('Customer message'))

@section('content')
<div class="t-page-head">
  <div><h1 class="page-title">{{ $ticket->subject }}</h1><p>{{ $ticket->event?->title ?: __('Event question') }} · {{ $ticket->email }}</p></div>
  <a class="t-btn t-btn--secondary" href="{{ route('organizer.customer_messages.index') }}">{{ __('All messages') }}</a>
</div>

@if(session('success'))<div class="t-card" role="status">{{ session('success') }}</div>@endif

<section class="t-card" style="max-width:900px">
  <div class="t-card__head"><h2 class="t-card__title">{{ __('Conversation') }}</h2><span class="t-badge {{ (int)$ticket->status === 3 ? '' : 't-badge--blue' }}">{{ (int)$ticket->status === 3 ? __('Closed') : __('Open') }}</span></div>
  <div style="display:grid;gap:14px;margin:18px 0">
    <article style="max-width:78%;padding:14px 16px;border-radius:14px;background:#f3f6fb"><b>{{ $ticket->customer?->fname ?: __('Customer') }}</b><p style="margin:6px 0 0">{{ $ticket->description }}</p>@if($ticket->attachment)<a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.customer_messages.attachment', $ticket) }}">{{ __('Download attachment') }}</a>@endif<small class="t-muted">{{ optional($ticket->created_at)->format('j M Y, g:i a') }}</small></article>
    @foreach($ticket->messages as $message)
      <article style="max-width:78%;padding:14px 16px;border-radius:14px;{{ (int)$message->type === 3 ? 'margin-left:auto;background:#eaf0ff' : 'background:#f3f6fb' }}">
        <b>{{ (int)$message->type === 3 ? __('You') : __('Customer') }}</b><div style="margin:6px 0">{!! $message->reply !!}</div>@if($message->file)<a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.customer_messages.reply_attachment', $message) }}">{{ __('Download attachment') }}</a>@endif<small class="t-muted">{{ optional($message->created_at)->format('j M Y, g:i a') }}</small>
      </article>
    @endforeach
  </div>

  @if((int)$ticket->status !== 3)
  <form method="POST" action="{{ route('organizer.customer_messages.reply', $ticket) }}" enctype="multipart/form-data" style="display:grid;gap:12px">
    @csrf
    <label class="t-field"><span class="t-label">{{ __('Reply') }}</span><textarea class="t-textarea" name="reply" rows="4" required>{{ old('reply') }}</textarea></label>
    @error('reply')<p class="text-danger">{{ $message }}</p>@enderror
    <label class="t-field"><span class="t-label">{{ __('ZIP attachment') }} <small class="t-muted">{{ __('optional') }}</small></span><input class="t-input" type="file" name="file" accept=".zip"></label>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><button class="t-btn t-btn--primary" type="submit">{{ __('Send reply') }}</button></div>
  </form>
  <form method="POST" action="{{ route('organizer.customer_messages.close', $ticket) }}" style="margin-top:12px">@csrf<button class="t-btn t-btn--ghost" type="submit">{{ __('Close conversation') }}</button></form>
  @endif
</section>
@endsection
