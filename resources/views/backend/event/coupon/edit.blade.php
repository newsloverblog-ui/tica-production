<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editCouponTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title" id="editCouponTitle">{{ __('Edit coupon') }}</h5><p>{{ __('Update the code, discount or active dates.') }}</p></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="ajaxEditForm" class="modal-form" action="{{ route('admin.event_management.update_coupon') }}"
                    method="post">

                    @csrf
                    <input type="hidden" id="in_id" name="id">

                    <div class="coupon-form-grid">
                            <div class="form-group">
                                <label for="in_name">{{ __('Name') }} <span aria-hidden="true">*</span></label>
                                <input type="text" id="in_name" class="form-control" name="name"
                                    placeholder="{{ __('e.g. Summer offer') }}">
                                <p id="editErr_name" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                            <div class="form-group">
                                <label for="in_code">{{ __('Code') }} <span aria-hidden="true">*</span></label>
                                <input type="text" id="in_code" class="form-control coupon-code-input" name="code"
                                    placeholder="{{ __('e.g. SUMMER20') }}">
                                <p id="editErr_code" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                            <div class="form-group">
                                <label for="in_type">{{ __('Discount type') }} <span aria-hidden="true">*</span></label>
                                <select name="type" id="in_type" class="form-control">
                                    <option disabled>{{ __('Choose type') }}</option>
                                    <option value="fixed">{{ __('Fixed') }}</option>
                                    <option value="percentage">{{ __('Percentage') }}</option>
                                </select>
                                <p id="editErr_type" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                            <div class="form-group">
                                <label for="in_value">{{ __('Discount value') }} <span aria-hidden="true">*</span></label>
                                <input type="number" min="0" step="0.01" id="in_value" class="form-control" name="value"
                                    placeholder="{{ __('Enter value') }}">
                                <p id="editErr_value" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                            <div class="form-group">
                                <label for="in_start_date">{{ __('Start date') }} <span aria-hidden="true">*</span></label>
                                <input type="text" id="in_start_date" class="form-control datepicker"
                                    name="start_date" placeholder="{{ __('Choose date') }}">
                                <p id="editErr_start_date" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                            <div class="form-group">
                                <label for="in_end_date">{{ __('End date') }} <span aria-hidden="true">*</span></label>
                                <input type="text" id="in_end_date" class="form-control datepicker" name="end_date"
                                    placeholder="{{ __('Choose date') }}">
                                <p id="editErr_end_date" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                            <div class="form-group coupon-form-field--wide">
                                <label for="in_events">{{ __('Events') }}</label>
                                <select id="in_events" class="select2" name="events[]" multiple="multiple"
                                    data-placeholder="{{ __('Choose events') }}">
                                    @foreach ($events as $event)
                                        <option value="{{ $event->id }}">
                                            {{ optional($event->information)->title ?: __('Event #:id', ['id' => $event->id]) }}
                                            ({{ $event->organizer?->username ?: __('TICA') }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="coupon-field-help">{{ __('No selection applies the coupon to all events.') }}</p>
                                <p id="editErr_events" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="coupon-btn coupon-btn--quiet" data-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
                <button id="updateBtn" type="button" class="coupon-btn coupon-btn--primary">
                    {{ __('Save changes') }}
                </button>
            </div>
        </div>
    </div>
</div>
