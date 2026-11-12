@extends('backend.layout')

@section('content')
  <div class="page-header"><h4 class="page-title">{{ __('Organizer Verification') }}</h4><ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="#">{{ __('KYC reviews') }}</a></li></ul></div>
  <div class="card">
    <div class="card-header"><div class="row align-items-center"><div class="col-lg-6"><div class="card-title">{{ __('Verification submissions') }}</div></div><div class="col-lg-6"><form method="get" class="form-inline justify-content-lg-end"><select name="status" class="form-control mr-2"><option value="">{{ __('All statuses') }}</option>@foreach (['pending', 'verified', 'rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select><button class="btn btn-primary">{{ __('Filter') }}</button></form></div></div></div>
    <div class="card-body"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>{{ __('Organizer') }}</th><th>{{ __('Legal name') }}</th><th>{{ __('Entity') }}</th><th>{{ __('Status') }}</th><th>{{ __('Submitted') }}</th><th></th></tr></thead><tbody>
      @forelse ($submissions as $submission)
        <tr><td>{{ optional($submission->organizer)->username ?: '—' }}<br><small>{{ optional($submission->organizer)->email }}</small></td><td>{{ $submission->legal_name }}</td><td>{{ __(ucfirst($submission->entity_type)) }}</td><td><span class="badge badge-{{ $submission->status === 'verified' ? 'success' : ($submission->status === 'rejected' ? 'danger' : 'warning') }}">{{ __(ucfirst($submission->status)) }}</span></td><td>{{ $submission->submitted_at->format('Y-m-d H:i') }}</td><td><a class="btn btn-sm btn-secondary" href="{{ route('admin.organizer_kyc.show', $submission) }}">{{ __('Review') }}</a></td></tr>
      @empty
        <tr><td colspan="6" class="text-center">{{ __('No verification submissions found.') }}</td></tr>
      @endforelse
    </tbody></table></div>{{ $submissions->links() }}</div>
  </div>
@endsection
