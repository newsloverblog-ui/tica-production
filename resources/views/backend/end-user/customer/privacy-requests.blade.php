@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Customer Privacy Requests') }}</h4>
    <ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li></ul>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title">{{ __('Deletion review queue') }}</div>
      <form method="GET" class="form-inline">
        <select name="status" class="form-control form-control-sm mr-2">
          <option value="">{{ __('All statuses') }}</option>
          @foreach (['pending', 'under_review', 'rejected', 'fulfilled', 'cancelled'] as $option)
            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
          @endforeach
        </select>
        <button class="btn btn-primary btn-sm" type="submit">{{ __('Filter') }}</button>
      </form>
    </div>
    <div class="card-body">
      <div class="alert alert-warning">
        {{ __('Never mark a request fulfilled until approved deletion/anonymization work is complete and retention exceptions are documented. Fulfilment archives the customer account but preserves required financial and audit history.') }}
      </div>
      @forelse ($requests as $privacyRequest)
        <div class="border rounded p-3 mb-4">
          <div class="row">
            <div class="col-md-4">
              <strong>{{ $privacyRequest->customer_name ?: __('Customer') }}</strong><br>
              <span>{{ $privacyRequest->customer_email }}</span><br>
              <code>{{ $privacyRequest->public_id }}</code><br>
              <span class="badge badge-info mt-2">{{ ucfirst(str_replace('_', ' ', $privacyRequest->status)) }}</span>
              <p class="mt-3 mb-1"><strong>{{ __('Submitted:') }}</strong> {{ $privacyRequest->submitted_at?->format('Y-m-d H:i:s T') }}</p>
              <p class="mb-1"><strong>{{ __('Policy:') }}</strong> {{ $privacyRequest->policy_version }}</p>
              @if ($privacyRequest->reason)<p class="mt-2"><strong>{{ __('Customer reason:') }}</strong><br>{{ $privacyRequest->reason }}</p>@endif
            </div>
            <div class="col-md-8">
              @if (in_array($privacyRequest->status, ['pending', 'under_review'], true))
                <form method="POST" action="{{ route('admin.customer_privacy_requests.update', $privacyRequest) }}">
                  @csrf
                  <div class="form-group">
                    <label>{{ __('Decision') }}</label>
                    <select name="status" class="form-control" required>
                      @if ($privacyRequest->status === 'pending')
                        <option value="under_review">{{ __('Move under review') }}</option>
                      @endif
                      <option value="rejected">{{ __('Reject with reason') }}</option>
                      @if ($privacyRequest->status === 'under_review')
                        <option value="fulfilled">{{ __('Fulfilled — archive account') }}</option>
                      @endif
                    </select>
                  </div>
                  <div class="form-group">
                    <label>{{ __('Resolution and retention decision') }}</label>
                    <textarea class="form-control" name="resolution" minlength="20" maxlength="4000" rows="4" required>{{ old('resolution') }}</textarea>
                  </div>
                  <div class="form-group">
                    <label>{{ __('Completion evidence reference') }}</label>
                    <input class="form-control" name="evidence_reference" maxlength="500" placeholder="Required only for fulfilment; do not enter secrets">
                  </div>
                  <button class="btn btn-primary" type="submit">{{ __('Record review decision') }}</button>
                </form>
              @else
                <p><strong>{{ __('Resolution:') }}</strong><br>{{ $privacyRequest->resolution ?: __('Not recorded') }}</p>
                @if ($privacyRequest->evidence_reference)<p><strong>{{ __('Evidence:') }}</strong> {{ $privacyRequest->evidence_reference }}</p>@endif
              @endif
            </div>
          </div>
        </div>
      @empty
        <p>{{ __('No privacy requests match this filter.') }}</p>
      @endforelse
      {{ $requests->links() }}
    </div>
  </div>
@endsection
