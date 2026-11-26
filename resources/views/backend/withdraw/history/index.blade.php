@extends('backend.layout')

@section('content')
  @php
    $queueUrl = route('admin.withdraw.withdraw_request');
    $filters = request()->only(['search', 'status', 'from_date', 'to_date', 'language']);
    $hasFilters = request()->anyFilled(['search', 'status', 'from_date', 'to_date']);
    $returnFilters = array_filter(request()->only(['search', 'status', 'from_date', 'to_date', 'language', 'page']),
      fn ($value) => $value !== '' && $value !== null);
  @endphp

  <section class="withdraw-queue" aria-labelledby="withdraw-queue-title">
    <header class="page-header">
      <h1 class="page-title" id="withdraw-queue-title">{{ __('Withdrawal requests') }}</h1>
    </header>

    @include('backend.withdraw.history.notices')

    <div class="card">
      <div class="card-header withdraw-queue__toolbar">
        <form class="withdraw-queue__filters" action="{{ $queueUrl }}" method="get" aria-label="{{ __('Filter withdrawals') }}">
          @if (request()->filled('language'))
            <input type="hidden" name="language" value="{{ request('language') }}">
          @endif
          <label>
            <span>{{ __('Withdrawal reference') }}</span>
            <input name="search" type="search" class="form-control" maxlength="100" placeholder="{{ __('Search reference') }}" value="{{ request('search') }}">
          </label>
          <label>
            <span>{{ __('Status') }}</span>
            <select name="status" class="form-control">
              <option value="">{{ __('All statuses') }}</option>
              @foreach ([0 => __('Pending or awaiting release'), 3 => __('On hold'), 4 => __('Processing'), 1 => __('Paid'), 2 => __('Rejected')] as $status => $label)
                <option value="{{ $status }}" @selected((string) request('status') === (string) $status)>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>
            <span>{{ __('From') }}</span>
            <input name="from_date" type="date" class="form-control" value="{{ request('from_date') }}">
          </label>
          <label>
            <span>{{ __('To') }}</span>
            <input name="to_date" type="date" class="form-control" value="{{ request('to_date') }}">
          </label>
          <div class="withdraw-queue__filter-actions">
            <button class="btn btn-primary" type="submit">{{ __('Apply filters') }}</button>
            @if ($hasFilters)
              <a class="btn btn-outline-secondary" href="{{ route('admin.withdraw.withdraw_request', request()->only('language')) }}">{{ __('Clear') }}</a>
            @endif
          </div>
        </form>

        <div class="withdraw-queue__tools">
          @if ($collection->isNotEmpty())
            <span class="t-num">{{ __('Showing :first–:last of :total', ['first' => $collection->firstItem(), 'last' => $collection->lastItem(), 'total' => number_format($collection->total())]) }}</span>
          @endif
          @if (Auth::guard('admin')->user()->hasPermission('Transaction'))
            <div class="withdraw-queue__exports">
              <form method="post" action="{{ route('admin.withdraw_exports.store') }}">
                @csrf
                @foreach ($filters as $key => $value)
                  <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label class="sr-only" for="withdraw-export-format">{{ __('Export format') }}</label>
                <select name="format" id="withdraw-export-format" class="form-control form-control-sm" required>
                  <option value="csv">CSV</option>
                  <option value="xlsx">XLSX</option>
                </select>
                <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('Queue private export') }}</button>
              </form>
              <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.withdraw_exports.index') }}">{{ __('Export history') }}</a>
            </div>
          @endif
        </div>
      </div>

      <div class="card-body">
        @if ($collection->isEmpty())
          <p class="withdraw-queue__empty">{{ $hasFilters ? __('No withdrawals match these filters.') : __('No withdrawal requests yet.') }}</p>
        @else
          <div class="withdraw-queue__results">
            <table class="withdraw-queue__table" role="table" aria-labelledby="withdraw-queue-title">
              <thead role="rowgroup">
                <tr role="row">
                  <th scope="col" role="columnheader">{{ __('Withdrawal') }}</th>
                  <th scope="col" role="columnheader" class="t-num">{{ __('Payable') }}</th>
                  <th scope="col" role="columnheader">{{ __('Status') }}</th>
                  <th scope="col" role="columnheader">{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody role="rowgroup">
                @foreach ($collection as $item)
                  @php
                    $review = $payoutReviews[$item->id] ?? null;
                    $currency = $item->payoutAccount?->currency ?? $currencyInfo->base_currency_text ?? 'NGN';
                    $action = match (true) {
                      !$review => __('View details'),
                      $review['can_queue'] || $review['can_record_external'] => __('Review & release'),
                      $review['approved'] => __('View approval'),
                      $review['can_approve'] => __('Review & approve'),
                      default => __('Review withdrawal'),
                    };
                  @endphp
                  <tr role="row">
                    <td role="cell">
                      <strong>{{ $item->organizer?->username ?? __('Organizer unavailable') }}</strong>
                      <span class="withdraw-queue__reference"><bdi>{{ $item->withdraw_id }}</bdi></span>
                      @if ($item->created_at)
                        <time class="withdraw-queue__meta" datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->translatedFormat('j M Y') }}</time>
                      @endif
                    </td>
                    <td role="cell" class="t-num">
                      <span class="withdraw-queue__cell-label" aria-hidden="true">{{ __('Payable') }}</span>
                      <strong>{{ number_format((float) $item->payable_amount, 2) }} {{ $currency }}</strong>
                    </td>
                    <td role="cell">
                      <span class="withdraw-queue__cell-label" aria-hidden="true">{{ __('Status') }}</span>
                      @include('backend.withdraw.history.status')
                    </td>
                    <td role="cell" class="withdraw-queue__action">
                      <a class="btn {{ $review ? 'btn-outline-primary' : 'btn-outline-secondary' }} btn-sm"
                        href="{{ route('admin.withdraw.review', ['id' => $item->id] + $returnFilters) }}">
                        {{ $action }}
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      @if ($collection->hasPages())
        <div class="card-footer withdraw-queue__pagination">{{ $collection->links() }}</div>
      @endif
    </div>

    @if ($payoutDisputes->isNotEmpty())
      <details class="card withdraw-queue__disputes" id="payout-disputes" @if (old('dispute_id')) open @endif>
        <summary class="card-header">{{ __('Open payout disputes') }} <span class="badge badge-warning t-num">{{ $payoutDisputes->count() }}</span></summary>
        <div class="card-body withdraw-queue__cases">
          @foreach ($payoutDisputes as $dispute)
            @php
              $retry = (string) old('dispute_id') === (string) $dispute->id;
              $reviewStatus = $retry ? old('status') : $dispute->status;
            @endphp
            <article class="withdraw-queue__case">
              <div>
                <strong>{{ __(ucwords(str_replace('_', ' ', $dispute->category))) }}</strong>
                <span class="badge badge-warning">{{ __(ucwords(str_replace('_', ' ', $dispute->status))) }}</span>
                <p class="withdraw-queue__meta"><bdi>{{ $dispute->public_id }}</bdi></p>
                @if ($dispute->withdrawal)
                  <p><a href="{{ route('admin.withdraw.review', ['id' => $dispute->withdrawal->id] + $returnFilters) }}">{{ __('View withdrawal') }}</a></p>
                @endif
                <p>{{ $dispute->organizer?->username ?? '#' . $dispute->organizer_id }} · {{ number_format((float) $dispute->amount, 2) }}</p>
                <p>{{ $dispute->description }}</p>
              </div>
              <form method="post" action="{{ route('admin.withdraw.disputes.review', $dispute) }}">
                @csrf
                <input type="hidden" name="dispute_id" value="{{ $dispute->id }}">
                <label for="dispute-status-{{ $dispute->id }}">{{ __('Review status') }}</label>
                <select class="form-control" name="status" id="dispute-status-{{ $dispute->id }}" required>
                  <option value="under_review" @selected($reviewStatus === 'under_review')>{{ __('Under review') }}</option>
                  <option value="resolved" @selected($reviewStatus === 'resolved')>{{ __('Resolved') }}</option>
                  <option value="rejected" @selected($reviewStatus === 'rejected')>{{ __('Rejected') }}</option>
                </select>
                <label for="dispute-resolution-{{ $dispute->id }}">{{ __('Resolution') }}</label>
                <textarea class="form-control" name="resolution" id="dispute-resolution-{{ $dispute->id }}" rows="3" maxlength="2000" placeholder="{{ __('Required when closing the dispute') }}">{{ $retry ? old('resolution') : $dispute->resolution }}</textarea>
                <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('Save dispute review') }}</button>
              </form>
            </article>
          @endforeach
        </div>
      </details>
    @endif
  </section>
@endsection
