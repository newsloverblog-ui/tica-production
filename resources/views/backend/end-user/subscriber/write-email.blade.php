@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Send Email') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{route('admin.dashboard')}}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('User Management') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Subscribers') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Send Email') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <form action="{{ route('admin.user_management.subscribers.send_email') }}" method="post">
          @csrf
          <div class="card-header">
            <div class="card-title d-inline-block">{{ __('Send Email') }}</div>
            <a class="btn btn-info btn-sm float-right d-inline-block" href="{{ route('admin.user_management.subscribers') }}">
              <span class="btn-label">
                <i class="fas fa-backward" ></i>
              </span>
              {{ __('Back') }}
            </a>
          </div>

          <div class="card-body pt-5">
            <div class="row">
              <div class="col-lg-8 offset-lg-2">
                <div class="form-group">
                  <label for="">{{ __('Subject') . '*' }}</label>
                  <input type="text" class="form-control" name="subject" placeholder="{{ __('Enter Mail Subject') }}">
                  @if ($errors->has('subject'))
                    <p class="mt-1 mb-0 text-danger">{{ $errors->first('subject') }}</p>
                  @endif
                </div>

                <div class="form-group">
                  <label for="">{{ __('Message') . '*' }}</label>
                  <textarea class="summernote" name="message" data-height="300" placeholder="{{ __('Write Your Mail') }}"></textarea>
                  @if ($errors->has('message'))
                    <p class="mb-0 text-danger">{{ $errors->first('message') }}</p>
                  @endif
                </div>
              </div>
            </div>
          </div>

          <div class="card-footer">
            <div class="row">
              <div class="col-12 text-center">
                <button type="submit" class="btn btn-success">
                  {{ __('Queue Campaign') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="card-title">{{ __('Recent Campaigns') }}</div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped mt-3">
              <thead>
                <tr>
                  <th>{{ __('Subject') }}</th>
                  <th>{{ __('Recipients') }}</th>
                  <th>{{ __('Sent') }}</th>
                  <th>{{ __('Failed') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th>{{ __('Queued') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($campaigns as $campaign)
                  <tr>
                    <td>{{ $campaign->subject }}</td>
                    <td>{{ number_format($campaign->total_count) }}</td>
                    <td>{{ number_format($campaign->sent_count) }}</td>
                    <td>{{ number_format($campaign->failed_count) }}</td>
                    <td><span class="badge badge-info">{{ __(str_replace('_', ' ', ucfirst($campaign->status))) }}</span></td>
                    <td>{{ optional($campaign->created_at)->format('d M Y, H:i') }}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center">{{ __('No campaigns have been queued yet.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
