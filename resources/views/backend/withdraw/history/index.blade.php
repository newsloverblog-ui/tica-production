@extends('backend.layout')

@section('content')
    <div class="page-header">
        <h4 class="page-title">{{ __('Withdraw Requests') }}</h4>
        <ul class="breadcrumbs">
            <li class="nav-home">
                <a href="{{ route('admin.dashboard') }}">
                    <i class="flaticon-home"></i>
                </a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="#">{{ __('Withdraw Method') }}</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="#">{{ __('Withdraw Requests') }}</a>
            </li>
        </ul>
    </div>

    @if ($payoutDisputes->isNotEmpty())
        <div class="row">
            <div class="col-md-12">
                <div class="card border-warning">
                    <div class="card-header">
                        <div class="card-title">{{ __('Open payout disputes') }}</div>
                    </div>
                    <div class="card-body table-responsive">
                        <p class="text-muted">{{ __('A dispute is a finance case record. Updating it does not move money, restore balance or mark a payout paid; use the controlled withdrawal actions separately after reconciling the evidence.') }}</p>
                        <table class="table table-bordered">
                            <thead><tr>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Withdrawal') }}</th>
                                <th>{{ __('Organizer') }}</th>
                                <th>{{ __('Issue') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Review') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($payoutDisputes as $dispute)
                                    <tr>
                                        <td><code>{{ $dispute->public_id }}</code><br><small>{{ optional($dispute->opened_at)->translatedFormat('M d, Y H:i') }}</small></td>
                                        <td>{{ optional($dispute->withdrawal)->withdraw_id ?: '#'.$dispute->withdrawal_id }}<br><small>{{ number_format((float) $dispute->amount, 2) }}</small></td>
                                        <td>{{ optional($dispute->organizer)->username ?: '#'.$dispute->organizer_id }}</td>
                                        <td>{{ __(ucwords(str_replace('_', ' ', $dispute->category))) }}<br><span class="badge badge-warning">{{ __(ucwords(str_replace('_', ' ', $dispute->status))) }}</span></td>
                                        <td style="min-width:260px; white-space:normal">{{ $dispute->description }}</td>
                                        <td style="min-width:300px">
                                            <form method="post" action="{{ route('admin.withdraw.disputes.review', $dispute) }}">
                                                @csrf
                                                <select class="form-control mb-2" name="status" required>
                                                    <option value="under_review" @selected($dispute->status === 'under_review')>{{ __('Under review') }}</option>
                                                    <option value="resolved">{{ __('Resolved') }}</option>
                                                    <option value="rejected">{{ __('Rejected') }}</option>
                                                </select>
                                                <textarea class="form-control mb-2" name="resolution" rows="3" maxlength="2000" placeholder="{{ __('Required when closing the dispute') }}">{{ $dispute->resolution }}</textarea>
                                                <button class="btn btn-primary btn-sm" type="submit">{{ __('Save review') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title mb-3">{{ __('Withdraw Requests') }}</div>
                    <form action="{{ route('admin.withdraw.withdraw_request') }}" method="GET" class="row align-items-end">
                        <div class="col-md-3 form-group"><label>{{ __('Withdrawal reference') }}</label><input name="search" type="search" class="form-control" maxlength="100" placeholder="{{ __('Search reference') }}" value="{{ request('search') }}"></div>
                        <div class="col-md-2 form-group"><label>{{ __('Status') }}</label><select name="status" class="form-control"><option value="">{{ __('All statuses') }}</option><option value="0" @selected(request('status') === '0')>{{ __('Pending') }}</option><option value="1" @selected(request('status') === '1')>{{ __('Paid') }}</option><option value="2" @selected(request('status') === '2')>{{ __('Rejected') }}</option><option value="3" @selected(request('status') === '3')>{{ __('On hold') }}</option><option value="4" @selected(request('status') === '4')>{{ __('Processing') }}</option></select></div>
                        <div class="col-md-2 form-group"><label>{{ __('From') }}</label><input name="from_date" type="date" class="form-control" value="{{ request('from_date') }}"></div>
                        <div class="col-md-2 form-group"><label>{{ __('To') }}</label><input name="to_date" type="date" class="form-control" value="{{ request('to_date') }}"></div>
                        <div class="col-md-3 form-group"><button class="btn btn-primary" type="submit">{{ __('Apply filters') }}</button> <a class="btn btn-outline-secondary" href="{{ route('admin.withdraw.withdraw_request') }}">{{ __('Reset') }}</a></div>
                    </form>
                    @if (Auth::guard('admin')->user()->hasPermission('Transaction'))
                        <div class="d-flex flex-wrap align-items-center border-top pt-3">
                            <form method="POST" action="{{ route('admin.withdraw_exports.store') }}" class="form-inline mr-2 mb-2">
                                @csrf
                                <input type="hidden" name="search" value="{{ request('search') }}"><input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="from_date" value="{{ request('from_date') }}"><input type="hidden" name="to_date" value="{{ request('to_date') }}">
                                <select name="format" class="form-control form-control-sm mr-2" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select>
                                <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-file-export"></i> {{ __('Queue private export') }}</button>
                            </form>
                            <a class="btn btn-outline-primary btn-sm mb-2" href="{{ route('admin.withdraw_exports.index') }}">{{ __('Export history') }}</a>
                        </div>
                    @endif
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-12">
                            @if (count($collection) == 0)
                                <h3 class="text-center">{{ __('NO WITHDRAW REQUESTS FOUND') . '!' }}</h3>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped mt-3">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th scope="col">{{ __('Withdraw Id') }}</th>
                                                <th scope="col">{{ __('Method Name') }}</th>
                                                <th scope="col">{{ __('Total Amount') }}</th>
                                                <th scope="col">{{ __('Total Charge') }}</th>
                                                <th scope="col">{{ __('Total Payable Amount') }}</th>
                                                <th scope="col">{{ __('Status') }}</th>
                                                <th scope="col">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($collection as $item)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $item->withdraw_id }}</td>
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
                                                        @elseif($item->status == 4)
                                                            <span class="badge badge-info">{{ __('Transfer processing') }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <a href="javascript:void(0)" data-toggle="modal"
                                                            data-target="#withdrawModal{{ $item->id }}"
                                                            class="btn btn-primary mt-1 btn-xs"><span class="btn-label">
                                                                <i class="fas fa-eye"></i>
                                                            </span> {{ __('View') }}</a>
                                                        @if (in_array((int) $item->status, [0, 3], true))
                                                            @if (config('payouts.paystack_transfers_enabled') && optional($item->payoutAccount)->provider === 'paystack' && optional($item->payoutAccount)->status === 'verified')
                                                                <button type="button" data-toggle="modal" data-target="#paystackModal{{ $item->id }}" class="btn btn-primary mt-1 btn-xs"><span class="btn-label"><i class="fas fa-paper-plane"></i></span> {{ __('Send via Paystack') }}</button>
                                                            @endif
                                                            <button type="button" data-toggle="modal" data-target="#paidModal{{ $item->id }}" class="btn btn-success mt-1 btn-xs"><span class="btn-label"><i class="fas fa-check-circle"></i></span> {{ __('Mark paid') }}</button>
                                                            @if ((int) $item->status === 0)
                                                                <button type="button" data-toggle="modal" data-target="#holdModal{{ $item->id }}" class="btn btn-secondary mt-1 btn-xs"><span class="btn-label"><i class="fas fa-pause-circle"></i></span> {{ __('Hold') }}</button>
                                                            @endif
                                                            <button type="button" data-toggle="modal" data-target="#rejectModal{{ $item->id }}" class="btn btn-warning mt-1 btn-xs"><span class="btn-label"><i class="fas fa-times"></i></span> {{ __('Reject') }}</button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="">
                                    {{ $collection->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-footer"></div>
            </div>
        </div>
    </div>

    {{-- edit modal --}}
    @include('backend.withdraw.history.view')
@endsection
