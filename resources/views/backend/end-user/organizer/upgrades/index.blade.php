@extends('backend.layout')

@section('content')
  <div class="page-header"><h4 class="page-title">{{ __('Organizer upgrade requests') }}</h4><ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="{{ route('admin.organizer_management.registered_organizer') }}">{{ __('Organizers') }}</a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="#">{{ __('Upgrade requests') }}</a></li></ul></div>

  @if (!\App\Services\AccountTypeGuard::upgradeEnabled())
    <div class="alert alert-info">{{ __('Customers can\'t ask to become organizers right now. Turn it on in Platform settings > Organiser sign-up.') }} <a href="{{ route('admin.tica.platform') }}#ta-organisers">{{ __('Open Platform settings') }}</a></div>
  @endif

  <div class="card">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-lg-6"><div class="card-title">{{ __('Customers asking to sell tickets') }}</div><small class="text-muted">{{ __('Approving moves the customer\'s email, password, tickets and orders into a new organizer account.') }}</small></div>
        <div class="col-lg-6 text-lg-right mt-2 mt-lg-0">
          <div class="btn-group" role="group" aria-label="{{ __('Filter by status') }}">
            @foreach (['pending' => __('Waiting'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'all' => __('All')] as $key => $label)
              <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.organizer_upgrades.index', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}@if ($key !== 'all' && !empty($counts[$key])) ({{ number_format($counts[$key]) }})@endif</a>
            @endforeach
          </div>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Organisation') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Asked') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Decision') }}</th></tr></thead>
          <tbody>
            @forelse ($requests as $upgrade)
              @php($c = $upgrade->customer)
              <tr>
                <td>{{ $upgrade->contact_name }}<br><small>{{ $c?->email ?: '—' }}</small>@if ($c?->provider)<br><small class="text-muted">{{ __('Signs in with :p', ['p' => ucfirst($c->provider)]) }}</small>@endif</td>
                <td>{{ $upgrade->organisation }}</td>
                <td>{{ $upgrade->phone }}</td>
                <td>{{ $upgrade->created_at?->format('j M Y, H:i') }}</td>
                <td>
                  <span class="badge badge-{{ $upgrade->status === 'approved' ? 'success' : ($upgrade->status === 'rejected' ? 'danger' : 'warning') }}">{{ ['pending' => __('Waiting'), 'approved' => __('Approved'), 'rejected' => __('Rejected')][$upgrade->status] ?? $upgrade->status }}</span>
                  @if ($upgrade->status === 'rejected' && $upgrade->reason)<br><small>{{ $upgrade->reason }}</small>@endif
                </td>
                <td class="text-right" style="min-width:260px">
                  @if ($upgrade->status === 'pending')
                    <form method="post" action="{{ route('admin.organizer_upgrades.decide', $upgrade) }}" class="d-inline" onsubmit="return confirm('{{ __('Approve and turn this customer into an organizer? Their customer log-in will switch off.') }}')">
                      @csrf<input type="hidden" name="decision" value="approve">
                      <button class="btn btn-sm btn-success" type="submit">{{ __('Approve') }}</button>
                    </form>
                    <details class="d-inline-block text-left ml-1">
                      <summary class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</summary>
                      <form method="post" action="{{ route('admin.organizer_upgrades.decide', $upgrade) }}" class="mt-2">
                        @csrf<input type="hidden" name="decision" value="reject">
                        <label class="small mb-1" for="reason-{{ $upgrade->id }}">{{ __('Reason (emailed to the customer)') }}</label>
                        <textarea class="form-control form-control-sm" id="reason-{{ $upgrade->id }}" name="reason" rows="3" maxlength="1000" required></textarea>
                        <button class="btn btn-sm btn-danger mt-2" type="submit">{{ __('Reject request') }}</button>
                      </form>
                    </details>
                  @elseif ($upgrade->organizer_id)
                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.organizer_management.organizer_details', ['id' => $upgrade->organizer_id]) }}">{{ __('View organizer') }}</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center">{{ $status === 'pending' ? __('No requests waiting.') : __('No requests found.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $requests->links() }}
    </div>
  </div>
@endsection
