@extends('organizer.layout')
@section('content')
  <div class="t-page-head"><div><h1 class="page-title">{{ __('Request') }} #{{ $ticket->id }}</h1><p>{{ $ticket->subject }}</p></div><a class="t-btn t-btn--secondary" href="{{ route('organizer.support_tickets') }}">{{ __('All requests') }}</a></div>
  <div class="row">
    <div class="col-md-12">
      <section class="t-card lg-ticket" aria-label="{{ __('Your request') }}">
        <div class="t-card__head">
          <span class="t-help">{{ __('Sent') }} {{ $ticket->created_at->format('j M Y, g:i a') }}</span>
          @if ((int) $ticket->status === 1)<span class="t-badge t-badge--orange">{{ __('Waiting for TICA') }}</span>
          @elseif ((int) $ticket->status === 2)<span class="t-badge t-badge--blue">{{ __('Open') }}</span>
          @else<span class="t-badge">{{ __('Closed') }}</span>@endif
        </div>
        <p class="lg-ticket__body">{{ $ticket->description }}</p>
        @if ($ticket->attachment)
          <a href="{{ asset('assets/admin/img/support-ticket/attachment/' . $ticket->attachment) }}" download="support_file" class="t-btn t-btn--secondary t-btn--sm">{{ __('Download attachment') }}</a>
        @endif
      </section>
      </div>
    </div>

    <div class="{{ $ticket->status == 3 ? 'col-lg-12' : 'col-lg-6' }}">
      <div class="card card-round">
        <div class="card-body">
          <div class="card-title fw-mediumbold">{{ __('Replies') }}</div>
          <div class="card-list">
            <div class="messages-container">
              @if (count($ticket->messages) > 0)
                @foreach ($ticket->messages as $reply)
                  @if ($reply->type == 2)
                    @php
                      $admin = App\Models\Admin::where('id', $reply->user_id)->first();
                    @endphp
                    <div class="item-list">
                      <div class="avatar">
                        <img
                          src="{{ $admin ? asset('assets/admin/img/admins/' . $admin->image) : asset('assets/admin/img/propics/blank_user.jpg') }}"
                          alt="..." class="avatar-img rounded-circle">
                      </div>
                      <div class="info-user ml-3">
                        <div class="username">{{ optional($admin)->username ?: __('TICA support') }}</div>
                        <div class="status">{{ $admin && $admin->id != 1 ? optional($admin->role)->name : __('TICA') }}</div>
                        <p>{!! $reply->reply !!}</p>
                        @if ($reply->file)
                          <a href="{{ asset('assets/admin/img/support-ticket/' . $reply->file) }}"
                            download="support_file" class="btn btn-rounded btn-info btn-sm">{{ __('Download') }}</a>
                        @endif
                      </div>
                    </div>
                  @else
                    @if ($reply->type == 3)
                      @php
                        $organizer = App\Models\Organizer::where('id', $reply->user_id)->first();
                      @endphp
                      <div class="item-list">
                        <div class="avatar">
                          @if ($organizer->photo)
                            <img class="avatar-img rounded-circle"
                              src="{{ $organizer->avatar_url }}"
                              alt="user-photo">
                          @else
                            <img class="avatar-img rounded-circle" src="{{ asset('assets/img/blank-user.jpg') }}"
                              alt="user-photo">
                          @endif
                        </div>
                        <div class="info-user ml-3">
                          <div class="username">{{ $organizer->username }} </div>
                          <div class="status">{{ __('Organizer') }}</div>
                          <p>{!! $reply->reply !!}</p>
                          @if ($reply->file)
                            <a href="{{ asset('assets/admin/img/support-ticket/' . $reply->file) }}"
                              download="support_file" class="btn btn-rounded btn-info btn-sm">{{ __('Download') }}</a>
                          @endif
                        </div>
                      </div>
                    @endif
                  @endif
                @endforeach
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>


    @if ($ticket->status == 2)
      <div class="col-lg-6 message-type">
        <div class="card card-round">
          <div class="card-body">
            <div class="card-title fw-mediumbold mb-2">{{ __('Reply to Ticket') }}</div>
            <form action="{{ route('organizer.support_ticket.reply', $ticket->id) }}" id="ajaxform" method="POST"
              enctype="multipart/form-data">@csrf
              <div class="form-group">
                <label for="">{{ __('Message') . ' **' }}</label>
                <textarea id="descriptionTmce1" name="reply" class="summernote" data-height="200"></textarea>
                <p class="em text-danger mb-0" id="errreply"></p>
              </div>
              <div class="form-group">
                <label for="">{{ __('Attachment') }}</label>
                <div class="input-group">
                  <div class="custom-file">
                    <input type="file" name="file" class="custom-file-input"
                      data-href="{{ route('organizer.support_ticket.zip_file.upload') }}" name="file" id="zip_file"
                      accept=".zip">
                    <label class="custom-file-label" for="zip_file">{{ __('Choose file') }}</label>
                  </div>
                </div>
                <p class="em text-danger mb-0" id="errfile"></p>
                <p class="mb-0 show-name"><small></small></p>
                <div class="progress progress-sm d-none">
                  <div class="progress-bar bg-success " role="progressbar" aria-valuenow="" aria-valuemin="0"
                    aria-valuemax=""></div>
                </div>
                <p class="text-warning">{{ __('Upload only ZIP Files, Max File Size is 5 MB') }}</p>
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-success">{{ __('Message') }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endif
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/admin/js/support.js') }}"></script>
@endsection
