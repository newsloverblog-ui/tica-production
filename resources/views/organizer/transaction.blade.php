@extends('organizer.layout')


@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Transactions') }}</h4>
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
        <a href="#">{{ __('Transactions') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row mb-3">
            <div class="col-lg-12">
              <div class="card-title d-inline-block">{{ __('Transactions') }}</div>
            </div>
          </div>
          <form action="{{ route('organizer.transcation') }}" method="get" class="row align-items-end">
            <div class="col-md-3 form-group"><label>{{ __('Transaction reference') }}</label><input type="search" maxlength="100" value="{{ request('transcation_id') }}" name="transcation_id" placeholder="{{ __('Search reference') }}" class="form-control"></div>
            <div class="col-md-2 form-group"><label>{{ __('Type') }}</label><select name="transaction_type" class="form-control"><option value="">{{ __('All types') }}</option>@foreach([1=>'Event booking',2=>'Product order',3=>'Withdrawal',4=>'Balance credit',5=>'Balance debit',6=>'Event refund',7=>'Product refund'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('transaction_type')===(string)$value)>{{ __($label) }}</option>@endforeach</select></div>
            <div class="col-md-2 form-group"><label>{{ __('Status') }}</label><select name="payment_status" class="form-control"><option value="">{{ __('All statuses') }}</option><option value="0" @selected(request('payment_status')==='0')>{{ __('Unpaid') }}</option><option value="1" @selected(request('payment_status')==='1')>{{ __('Paid') }}</option><option value="2" @selected(request('payment_status')==='2')>{{ __('Declined') }}</option></select></div>
            <div class="col-md-2 form-group"><label>{{ __('From') }}</label><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
            <div class="col-md-2 form-group"><label>{{ __('To') }}</label><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
            <div class="col-md-1 form-group"><button class="btn btn-primary btn-block" type="submit">{{ __('Filter') }}</button></div>
          </form>
          <div class="d-flex flex-wrap align-items-center border-top pt-3">
            <form method="POST" action="{{ route('organizer.transaction_exports.store') }}" class="form-inline mr-2 mb-2">@csrf
              <input type="hidden" name="transcation_id" value="{{ request('transcation_id') }}"><input type="hidden" name="transaction_type" value="{{ request('transaction_type') }}"><input type="hidden" name="payment_status" value="{{ request('payment_status') }}"><input type="hidden" name="from_date" value="{{ request('from_date') }}"><input type="hidden" name="to_date" value="{{ request('to_date') }}">
              <select name="format" class="form-control form-control-sm mr-2" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select><button class="btn btn-success btn-sm" type="submit"><i class="fas fa-file-export"></i> {{ __('Queue private export') }}</button>
            </form><a class="btn btn-outline-primary btn-sm mb-2 mr-2" href="{{ route('organizer.transaction_exports.index') }}">{{ __('Export history') }}</a><a class="btn btn-outline-secondary btn-sm mb-2" href="{{ route('organizer.transcation') }}">{{ __('Reset filters') }}</a>
          </div>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-lg-12">
              @if (count($transcations) == 0)
                <h3 class="text-center mt-3">{{ __('NO TRANSCATION FOUND') . '!' }}</h3>
              @else
                <div class="table-responsive">
                  <table class="table table-striped mt-3">
                    <thead>
                      <tr>
                        <th scope="col">{{ __('Transaction Id') }}</th>
                        <th scope="col">{{ __('Transaction Type') }}</th>
                        <th scope="col">{{ __('Payment Method') }}</th>
                        <th scope="col">{{ __('Pre Balance') }}</th>
                        <th scope="col">{{ __('Amount') }}</th>
                        <th scope="col">{{ __('After Balance') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($transcations as $transcation)
                        <tr>
                          <td>#{{ $transcation->transcation_id }}</td>
                          <td>
                            @if ($transcation->transcation_type == 1)
                              {{ 'Event Booking' }}
                            @elseif ($transcation->transcation_type == 2)
                              {{ 'Product Order' }}
                            @elseif ($transcation->transcation_type == 3)
                              {{ 'Withdraw' }}
                            @elseif ($transcation->transcation_type == 4)
                              {{ 'Balance Add' }}
                            @elseif ($transcation->transcation_type == 5)
                              {{ 'Balance Subtract' }}
                            @elseif ($transcation->transcation_type == 6)
                              {{ 'Payment Refund' }}
                            @endif
                          </td>
                          <td>
                            @if ($transcation->transcation_type == 3)
                              @php
                                $method = $transcation->method()->first();
                              @endphp
                              @if ($method)
                                {{ $method->name }}
                              @else
                                {{ '-' }}
                              @endif
                            @else
                              {{ $transcation->payment_method != null ? $transcation->payment_method : '-' }}
                            @endif
                          </td>
                          <td>
                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->pre_balance }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            @if (in_array($transcation->transcation_type, [3, 5, 6]))
                              <span class="text-danger">{{ '(-) ' }}</span>
                            @else
                              <span class="text-success">{{ '(+) ' }}</span>
                            @endif

                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->grand_total - $transcation->commission }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->after_balance }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            @if ($transcation->payment_status == 1)
                              <span class="badge badge-success">{{ __('Paid') }}</span>
                            @elseif ($transcation->payment_status == 2)
                              <span class="badge badge-warning">{{ __('Decline') }}</span>
                            @else
                              <span class="badge badge-danger">{{ __('Unpaid') }}</span>
                            @endif
                          </td>

                          <td>
                            @if ($transcation->transcation_type == 1)
                              @php
                                $t_invoice = $transcation->event_booking()->first();
                              @endphp
                              @if ($t_invoice)
                                <a target="_blank" class="btn btn-secondary btn-sm mr-1"
                                  href="{{ route('organizer.event_booking.invoice', $t_invoice->id) }}">
                                  <i class="fas fa-eye"></i>
                                </a>
                              @endif
                            @elseif ($transcation->transcation_type == 2)
                              {{ '-' }}
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="card-footer text-center">
          <div class="d-inline-block mt-3">
            {{ $transcations->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
