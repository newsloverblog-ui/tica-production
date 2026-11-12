@extends('backend.layout')

@section('content')
  <div class="page-header">
    <div><p class="tica-eyebrow">{{ __('Advertising governance') }}</p><h1 class="h3 mb-1">{{ __('TICA promotions') }}</h1><p class="text-muted mb-0">{{ __('Set prices, review paid placements and approve provider tracking IDs. Organizers can never submit executable scripts.') }}</p></div>
  </div>

  <section class="card mb-4" aria-labelledby="promotion-prices-title">
    <div class="card-header"><h2 class="card-title mb-0" id="promotion-prices-title">{{ __('Plans and pricing') }}</h2></div>
    <div class="card-body"><div class="row">
      @foreach ($plans as $plan)
        <div class="col-xl-4 col-md-6 mb-3">
          <form method="post" action="{{ route('admin.advertise.tica_promotions.plan', $plan) }}" class="border rounded p-3 h-100">
            @csrf
            <p class="small text-uppercase text-muted mb-2">{{ str_replace('_', ' ', $plan->placement) }}</p>
            <div class="form-group"><label for="plan-name-{{ $plan->id }}">{{ __('Name') }}</label><input class="form-control" id="plan-name-{{ $plan->id }}" name="name" value="{{ $plan->name }}" required maxlength="100"></div>
            <div class="form-group"><label for="plan-description-{{ $plan->id }}">{{ __('Description') }}</label><textarea class="form-control" id="plan-description-{{ $plan->id }}" name="description" maxlength="500">{{ $plan->description }}</textarea></div>
            <div class="row"><div class="col-7 form-group"><label for="plan-price-{{ $plan->id }}">{{ __('Price') }} ({{ \App\Support\TicaCurrency::base() }})</label><input class="form-control" id="plan-price-{{ $plan->id }}" name="price" type="number" min="0" step="0.01" value="{{ $plan->price }}" required></div><div class="col-5 form-group"><label for="plan-days-{{ $plan->id }}">{{ __('Days') }}</label><input class="form-control" id="plan-days-{{ $plan->id }}" name="duration_days" type="number" min="1" max="90" value="{{ $plan->duration_days }}" required></div></div>
            <div class="custom-control custom-switch mb-3"><input class="custom-control-input" id="plan-active-{{ $plan->id }}" name="is_active" value="1" type="checkbox" @checked($plan->is_active)><label class="custom-control-label" for="plan-active-{{ $plan->id }}">{{ __('Available to organizers') }}</label></div>
            <div class="form-group"><label for="plan-password-{{ $plan->id }}">{{ __('Admin password') }}</label><input class="form-control" id="plan-password-{{ $plan->id }}" name="current_password" type="password" autocomplete="current-password" required></div>
            <button class="btn btn-primary" type="submit">{{ __('Save price') }}</button>
          </form>
        </div>
      @endforeach
    </div></div>
  </section>

  <section class="card mb-4" aria-labelledby="campaign-review-title">
    <div class="card-header"><h2 class="card-title mb-0" id="campaign-review-title">{{ __('Promotion requests') }}</h2></div>
    <div class="card-body table-responsive">
      <table class="table"><thead><tr><th>{{ __('Organizer / event') }}</th><th>{{ __('Placement') }}</th><th>{{ __('Price') }}</th><th>{{ __('Status') }}</th><th>{{ __('Review') }}</th></tr></thead><tbody>
      @forelse ($campaigns as $campaign)
        <tr><td><strong>{{ $campaign->organizer->username ?? __('Organizer') }}</strong><small class="d-block text-muted">{{ optional(optional($campaign->event)->information)->title ?? __('Event') }}</small></td><td>{{ $campaign->plan->name ?? '—' }}<small class="d-block text-muted">{{ optional($campaign->starts_at)->format('j M Y') }} – {{ optional($campaign->ends_at)->format('j M Y') }}</small></td><td>{{ $campaign->currency }} {{ number_format((float) $campaign->price, 2) }}<small class="d-block {{ $campaign->paid_at ? 'text-success' : 'text-warning' }}">{{ $campaign->paid_at ? __('Paid') : __('Not paid') }}</small></td><td><span class="badge badge-secondary">{{ str_replace('_', ' ', $campaign->status) }}</span></td><td><form method="post" action="{{ route('admin.advertise.tica_promotions.campaign', $campaign) }}" class="d-flex flex-wrap" style="gap:6px">@csrf<select class="form-control form-control-sm" name="decision" style="width:auto"><option value="approve">{{ __('Approve') }}</option><option value="reject">{{ __('Reject') }}</option><option value="pause">{{ __('Pause') }}</option></select><input class="form-control form-control-sm" name="admin_notes" maxlength="2000" placeholder="{{ __('Review note') }}" style="width:160px"><input class="form-control form-control-sm" name="current_password" type="password" autocomplete="current-password" required placeholder="{{ __('Admin password') }}" style="width:150px"><button class="btn btn-primary btn-sm" type="submit">{{ __('Save') }}</button></form></td></tr>
      @empty<tr><td colspan="5" class="text-center text-muted">{{ __('No promotion requests yet.') }}</td></tr>@endforelse
      </tbody></table>
    </div>
  </section>

  <section class="card" aria-labelledby="pixel-review-title">
    <div class="card-header"><h2 class="card-title mb-0" id="pixel-review-title">{{ __('Tracking provider IDs') }}</h2></div>
    <div class="card-body table-responsive"><table class="table"><thead><tr><th>{{ __('Provider') }}</th><th>{{ __('Label / ID') }}</th><th>{{ __('Status') }}</th><th>{{ __('Review') }}</th></tr></thead><tbody>
      @forelse ($pixels as $pixel)
        <tr><td>{{ strtoupper($pixel->provider) }}</td><td><strong>{{ $pixel->label }}</strong><small class="d-block text-muted">{{ $pixel->pixel_identifier }}</small></td><td>{{ str_replace('_', ' ', $pixel->status) }}</td><td><form method="post" action="{{ route('admin.advertise.tica_promotions.pixel', $pixel) }}" class="d-flex flex-wrap" style="gap:6px">@csrf<select class="form-control form-control-sm" name="decision" style="width:auto"><option value="approve">{{ __('Approve') }}</option><option value="reject">{{ __('Reject') }}</option><option value="disable">{{ __('Disable') }}</option></select><input class="form-control form-control-sm" name="admin_notes" maxlength="2000" placeholder="{{ __('Review note') }}" style="width:150px"><input class="form-control form-control-sm" name="current_password" type="password" autocomplete="current-password" required placeholder="{{ __('Admin password') }}" style="width:150px"><button class="btn btn-primary btn-sm" type="submit">{{ __('Save') }}</button></form></td></tr>
      @empty<tr><td colspan="4" class="text-center text-muted">{{ __('No tracking IDs submitted.') }}</td></tr>@endforelse
    </tbody></table></div>
  </section>
@endsection
