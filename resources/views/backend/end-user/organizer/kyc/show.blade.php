@extends('backend.layout')

@section('content')
  <div class="page-header"><h4 class="page-title">{{ __('Review Organizer Verification') }}</h4><ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="{{ route('admin.organizer_kyc.index') }}">{{ __('KYC reviews') }}</a></li></ul></div>
  <div class="row">
    <div class="col-lg-7 mb-4"><div class="card"><div class="card-header"><div class="card-title">{{ __('Submitted identity') }}</div></div><div class="card-body">
      <dl class="row"><dt class="col-sm-4">{{ __('Organizer') }}</dt><dd class="col-sm-8">{{ $submission->organizer->username }} · {{ $submission->organizer->email }}</dd><dt class="col-sm-4">{{ __('Legal name') }}</dt><dd class="col-sm-8">{{ $submission->legal_name }}</dd><dt class="col-sm-4">{{ __('Entity') }}</dt><dd class="col-sm-8">{{ __(ucfirst($submission->entity_type)) }}</dd><dt class="col-sm-4">{{ __('Business') }}</dt><dd class="col-sm-8">{{ $submission->business_name ?: '—' }}</dd><dt class="col-sm-4">{{ __('Registration number') }}</dt><dd class="col-sm-8">{{ $submission->registration_number ?: '—' }}</dd><dt class="col-sm-4">{{ __('ID type') }}</dt><dd class="col-sm-8">{{ __(ucwords(str_replace('_', ' ', $submission->id_type))) }}</dd></dl>
      <h5>{{ __('Private documents') }}</h5>
      @foreach ($submission->documents as $document)<a class="btn btn-outline-secondary mr-2 mb-2" href="{{ route('admin.organizer_kyc.document', $document) }}"><i class="fas fa-download mr-1"></i>{{ __(ucwords(str_replace('_', ' ', $document->document_type))) }} <small>({{ number_format($document->size / 1024, 0) }} KB)</small></a>@endforeach
    </div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-header"><div class="card-title">{{ __('Decision') }}</div></div><div class="card-body">
      <p>{{ __('Current status:') }} <span class="badge badge-{{ $submission->status === 'verified' ? 'success' : ($submission->status === 'rejected' ? 'danger' : 'warning') }}">{{ __(ucfirst($submission->status)) }}</span></p>
      @if ($submission->status === 'pending')
        <form method="post" action="{{ route('admin.organizer_kyc.review', $submission) }}">@csrf<div class="form-group"><label>{{ __('Decision') }}</label><select name="decision" id="kycDecision" class="form-control" required><option value="verified">{{ __('Verify') }}</option><option value="rejected">{{ __('Reject') }}</option></select></div><div class="form-group" id="kycReason"><label>{{ __('Rejection reason') }}</label><textarea name="rejection_reason" class="form-control" rows="5" maxlength="2000"></textarea></div><button class="btn btn-primary">{{ __('Save decision') }}</button></form>
      @else
        <dl><dt>{{ __('Reviewed') }}</dt><dd>{{ optional($submission->reviewed_at)->format('Y-m-d H:i') ?: '—' }}</dd>@if ($submission->rejection_reason)<dt>{{ __('Reason') }}</dt><dd>{{ $submission->rejection_reason }}</dd>@endif</dl>
      @endif
    </div></div></div>
  </div>
@endsection

@section('script')
  <script>(function(){const decision=document.getElementById('kycDecision');const reason=document.getElementById('kycReason');if(!decision||!reason)return;const sync=()=>reason.style.display=decision.value==='rejected'?'':'none';decision.addEventListener('change',sync);sync();})();</script>
@endsection
