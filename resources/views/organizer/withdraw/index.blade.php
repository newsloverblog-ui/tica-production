@extends('organizer.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Withdraws') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('organizer.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('My Withdraws') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-4">
              <div class="card-title d-inline-block">
                {{ __('My Withdraws') }}
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card-title">{{ __('Your Balance') }} :
                {{ $settings->base_currency_symbol_position == 'left' ? $settings->base_currency_symbol : '' }}
                {{ Auth::guard('organizer')->user()->amount }}
                {{ $settings->base_currency_symbol_position == 'right' ? $settings->base_currency_symbol : '' }}</div>
            </div>
            <div class="col-lg-4 mt-2 mt-lg-0">

              <a href="{{ route('organizer.withdraw.create', ['language' => $defaultLang->code]) }}"
                class="btn btn-secondary btn-sm float-lg-right float-left">
                <i class="fas fa-plus"></i> {{ __('Withdraw Now')."!" }}
              </a>

              <button class="btn btn-danger btn-sm float-lg-right float-left mr-2 d-none bulk-delete"
                data-action="cancel-withdrawal"
                data-href="{{ route('organizer.witdraw.bulk_delete_withdraw') }}">
                <i class="fas fa-times"></i> {{ __('Cancel selected') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><div class="card-title">{{ __('Filter and export withdrawals') }}</div></div>
        <div class="card-body">
          <form action="{{ route('organizer.withdraw') }}" method="GET" class="row align-items-end">
            <div class="col-md-3 form-group"><label>{{ __('Withdrawal reference') }}</label><input name="search" type="search" maxlength="100" class="form-control" placeholder="{{ __('Search reference') }}" value="{{ request('search') }}"></div>
            <div class="col-md-2 form-group"><label>{{ __('Status') }}</label><select name="status" class="form-control"><option value="">{{ __('All statuses') }}</option><option value="0" @selected(request('status') === '0')>{{ __('Pending') }}</option><option value="1" @selected(request('status') === '1')>{{ __('Paid') }}</option><option value="2" @selected(request('status') === '2')>{{ __('Rejected') }}</option><option value="3" @selected(request('status') === '3')>{{ __('On hold') }}</option><option value="4" @selected(request('status') === '4')>{{ __('Processing') }}</option></select></div>
            <div class="col-md-2 form-group"><label>{{ __('From') }}</label><input name="from_date" type="date" class="form-control" value="{{ request('from_date') }}"></div>
            <div class="col-md-2 form-group"><label>{{ __('To') }}</label><input name="to_date" type="date" class="form-control" value="{{ request('to_date') }}"></div>
            <div class="col-md-3 form-group"><button class="btn btn-primary" type="submit">{{ __('Apply filters') }}</button> <a class="btn btn-outline-secondary" href="{{ route('organizer.withdraw') }}">{{ __('Reset') }}</a></div>
          </form>
          <div class="d-flex flex-wrap align-items-center border-top pt-3">
            <form method="POST" action="{{ route('organizer.withdraw_exports.store') }}" class="form-inline mr-2 mb-2">
              @csrf
              <input type="hidden" name="search" value="{{ request('search') }}"><input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="from_date" value="{{ request('from_date') }}"><input type="hidden" name="to_date" value="{{ request('to_date') }}">
              <select name="format" class="form-control form-control-sm mr-2" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select>
              <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-file-export"></i> {{ __('Queue private export') }}</button>
            </form>
            <a class="btn btn-outline-primary btn-sm mb-2" href="{{ route('organizer.withdraw_exports.index') }}">{{ __('Export history') }}</a>
          </div>
        </div>
      </div>

      <div class="card">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-12">

            @if (session()->has('course_status_warning'))
              <div class="alert alert-warning">
                <p class="text-dark mb-0">{{ session()->get('course_status_warning') }}</p>
              </div>
            @endif



            <div class="table-responsive">
              <table class="table table-striped mt-3" id="basic-datatables">
                <thead>
                  <tr>
                    <th scope="col">
                      <input type="checkbox" class="bulk-check" data-val="all">
                    </th>
                    <th scope="col">{{ __('Withdraw Id') }}</th>
                    <th scope="col">{{ __('Method Name') }}</th>
                    <th scope="col">{{ __('Total Amount') }}</th>
                    <th scope="col">{{ __('Total Charge') }}</th>
                    <th scope="col">{{ __('Total Payable Amount') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Dispute') }}</th>
                    <th scope="col">{{ __('Action') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($collection as $item)
                    <tr>
                      <td>
                        <input type="checkbox" class="bulk-check" data-val="{{ $item->id }}">
                      </td>
                      <td>
                        {{ $item->withdraw_id }}
                      </td>
                      <td>
                        {{ optional($item->method)->name }}
                      </td>
                      <td>
                        {{ $currencyInfo->base_currency_symbol_position == 'left' ? $currencyInfo->base_currency_symbol : '' }}
                        {{ $item->amount }}
                        {{ $currencyInfo->base_currency_symbol_position == 'right' ? $currencyInfo->base_currency_symbol : '' }}
                      </td>
                      <td>
                        {{ $currencyInfo->base_currency_symbol_position == 'left' ? $currencyInfo->base_currency_symbol : '' }}
                        {{ $item->total_charge }}
                        {{ $currencyInfo->base_currency_symbol_position == 'right' ? $currencyInfo->base_currency_symbol : '' }}
                      </td>
                      <td>
                        {{ $currencyInfo->base_currency_symbol_position == 'left' ? $currencyInfo->base_currency_symbol : '' }}
                        {{ $item->payable_amount }}
                        {{ $currencyInfo->base_currency_symbol_position == 'right' ? $currencyInfo->base_currency_symbol : '' }}
                      </td>
                      <td>
                        @if ($item->status == 0)
                          <span class="badge badge-danger">{{ __('Pending') }}</span>
                        @elseif($item->status == 1)
                          <span class="badge badge-success">{{ __('Paid') }}</span>
                        @elseif($item->status == 2)
                          <span class="badge badge-danger">{{ __('Rejected') }}</span>
                        @elseif($item->status == 3)
                          <span class="badge badge-warning">{{ __('On hold') }}</span>
                        @endif
                      </td>
                      <td>
                        @if ($item->latestPayoutDispute)
                          <span class="badge badge-{{ in_array($item->latestPayoutDispute->status, ['resolved']) ? 'success' : (in_array($item->latestPayoutDispute->status, ['rejected']) ? 'danger' : 'warning') }}">
                            {{ __(ucwords(str_replace('_', ' ', $item->latestPayoutDispute->status))) }}
                          </span>
                        @else
                          <span class="text-muted">{{ __('None') }}</span>
                        @endif
                      </td>
                      <td>
                        <a href="javascript:void(0)" data-toggle="modal" data-target="#withdrawModal{{ $item->id }}"
                          class="btn btn-primary btn-sm"><i class="fas fa-eye"></i></a>
                        @if ((int) $item->status === 0)
                          <form class="deleteForm d-inline-block"
                            action="{{ route('organizer.witdraw.delete_withdraw', ['id' => $item->id]) }}" method="post">
                            @csrf
                            <input type="hidden" name="id" value="{{ $item->id }}">
                            <button type="submit" class="btn btn-danger btn-sm deleteBtn" title="{{ __('Cancel pending request') }}"><i class="fas fa-times"></i></button>
                          </form>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card-footer"></div>
    </div>
  </div>
  </div>
  @foreach ($collection as $item)
    <div class="modal fade" id="withdrawModal{{ $item->id }}" tabindex="-1" role="dialog"
      aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title" id="exampleModalLongTitle">{{ __('Withdraw Information') }}</h4>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <div class="modal-body">
            @php
              $d_feilds = json_decode($item->feilds, true);
            @endphp
            <div class="">
              <p>{{ __('Total Payable Amount') }} :
                {{ $currencyInfo->base_currency_symbol_position == 'left' ? $currencyInfo->base_currency_symbol : '' }}
                {{ $item->payable_amount }}
                {{ $currencyInfo->base_currency_symbol_position == 'right' ? $currencyInfo->base_currency_symbol : '' }}
              </p>
              @foreach ($d_feilds as $key => $d_feild)
                <p><strong>{{ str_replace('_', ' ', $key) }} : {{ $d_feild }}</strong></p>
              @endforeach
            </div>

            <hr>
            <h5>{{ __('Payout dispute') }}</h5>
            @if ($item->latestPayoutDispute)
              <dl class="row mb-3">
                <dt class="col-sm-4">{{ __('Reference') }}</dt>
                <dd class="col-sm-8"><code>{{ $item->latestPayoutDispute->public_id }}</code></dd>
                <dt class="col-sm-4">{{ __('Status') }}</dt>
                <dd class="col-sm-8">{{ __(ucwords(str_replace('_', ' ', $item->latestPayoutDispute->status))) }}</dd>
                <dt class="col-sm-4">{{ __('Issue') }}</dt>
                <dd class="col-sm-8">{{ __(ucwords(str_replace('_', ' ', $item->latestPayoutDispute->category))) }}</dd>
                <dt class="col-sm-4">{{ __('Finance response') }}</dt>
                <dd class="col-sm-8">{{ $item->latestPayoutDispute->resolution ?: __('Awaiting finance review') }}</dd>
              </dl>
            @endif

            @if (!$item->latestPayoutDispute || in_array($item->latestPayoutDispute->status, ['resolved', 'rejected'], true))
              <form method="post" action="{{ route('organizer.withdraw.disputes.store', $item) }}">
                @csrf
                <div class="form-group">
                  <label for="dispute-category-{{ $item->id }}">{{ __('Issue category') }}</label>
                  <select id="dispute-category-{{ $item->id }}" class="form-control" name="category" required>
                    <option value="">{{ __('Select an issue') }}</option>
                    @foreach (\App\Models\PayoutDispute::CATEGORIES as $category)
                      <option value="{{ $category }}">{{ __(ucwords(str_replace('_', ' ', $category))) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label for="dispute-description-{{ $item->id }}">{{ __('What happened?') }}</label>
                  <textarea id="dispute-description-{{ $item->id }}" class="form-control" name="description" rows="4" minlength="20" maxlength="2000" required></textarea>
                  <small class="form-text text-muted">{{ __('Include dates and the expected outcome. Do not include bank PINs, passwords or card details.') }}</small>
                </div>
                <div class="form-check">
                  <input id="confirm-dispute-{{ $item->id }}" class="form-check-input" type="checkbox" name="confirm_dispute" value="1" required>
                  <label class="form-check-label" for="confirm-dispute-{{ $item->id }}">{{ __('I confirm these details are accurate and may be reviewed by finance.') }}</label>
                </div>
                <button type="submit" class="btn btn-warning btn-sm mt-3">{{ __('Open dispute') }}</button>
              </form>
            @else
              <div class="alert alert-info mb-0">{{ __('Finance is reviewing this dispute. A second dispute cannot be opened until it is closed.') }}</div>
            @endif
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
              {{ __('Close') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  @endforeach
@endsection
