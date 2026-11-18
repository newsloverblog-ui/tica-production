<div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-labelledby="createCouponTitle"
  aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div><h5 class="modal-title" id="createCouponTitle">{{ __('Create coupon') }}</h5><p>{{ __('Set the code, discount and active dates.') }}</p></div>
        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <form id="ajaxForm" class="modal-form" action="{{ route('admin.event_management.store_coupon') }}"
          method="post">
          @csrf
          <div class="coupon-form-grid">
            <div class="form-group">
                <label for="coupon_name">{{ __('Name') }} <span aria-hidden="true">*</span></label>
                <input id="coupon_name" type="text" class="form-control" name="name" placeholder="{{ __('e.g. Summer offer') }}">
                <p id="err_name" class="mt-1 mb-0 text-danger em"></p>
            </div>
            <div class="form-group">
                <label for="coupon_code">{{ __('Code') }} <span aria-hidden="true">*</span></label>
                <input id="coupon_code" type="text" class="form-control coupon-code-input" name="code" placeholder="{{ __('e.g. SUMMER20') }}">
                <p id="err_code" class="mt-1 mb-0 text-danger em"></p>
            </div>
            <div class="form-group">
                <label for="coupon_type">{{ __('Discount type') }} <span aria-hidden="true">*</span></label>
                <select id="coupon_type" name="type" class="form-control">
                  <option selected disabled>{{ __('Choose type') }}</option>
                  <option value="fixed">{{ __('Fixed') }}</option>
                  <option value="percentage">{{ __('Percentage') }}</option>
                </select>
                <p id="err_type" class="mt-1 mb-0 text-danger em"></p>
            </div>
            <div class="form-group">
                <label for="coupon_value">{{ __('Discount value') }} <span aria-hidden="true">*</span></label>
                <input id="coupon_value" type="number" min="0" step="0.01" class="form-control" name="value" placeholder="{{ __('Enter value') }}">
                <p id="err_value" class="mt-1 mb-0 text-danger em"></p>
            </div>
            <div class="form-group">
                <label for="coupon_start_date">{{ __('Start date') }} <span aria-hidden="true">*</span></label>
                <input id="coupon_start_date" type="text" class="form-control datepicker" name="start_date" placeholder="{{ __('Choose date') }}">
                <p id="err_start_date" class="mt-1 mb-0 text-danger em"></p>
            </div>
            <div class="form-group">
                <label for="coupon_end_date">{{ __('End date') }} <span aria-hidden="true">*</span></label>
                <input id="coupon_end_date" type="text" class="form-control datepicker" name="end_date" placeholder="{{ __('Choose date') }}">
                <p id="err_end_date" class="mt-1 mb-0 text-danger em"></p>
            </div>
            <div class="form-group coupon-form-field--wide">
                <label for="coupon_events">{{ __('Events') }}</label>
                <select id="coupon_events" class="select2" name="events[]" multiple="multiple" data-placeholder="{{ __('Choose events') }}">
                  @foreach ($events as $event)
                    <option value="{{ $event->id }}">
                      {{ optional($event->information)->title ?: __('Event #:id', ['id' => $event->id]) }}
                      ({{ $event->organizer?->username ?: __('TICA') }})
                    </option>
                  @endforeach
                </select>
                <p class="coupon-field-help">{{ __('No selection applies the coupon to all events.') }}</p>
                <p id="err_events" class="mb-0 text-danger em"></p>
            </div>
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="coupon-btn coupon-btn--quiet" data-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button id="submitBtn" type="button" class="coupon-btn coupon-btn--primary">
          {{ __('Create coupon') }}
        </button>
      </div>
    </div>
  </div>
</div>
