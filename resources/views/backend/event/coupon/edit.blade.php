<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Edit Coupon') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="ajaxEditForm" class="modal-form" action="{{ route('admin.event_management.update_coupon') }}"
                    method="post">

                    @csrf
                    <input type="hidden" id="in_id" name="id">

                    <div class="row no-gutters">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="">{{ __('Name') . '*' }}</label>
                                <input type="text" id="in_name" class="form-control" name="name"
                                    placeholder="{{ __('Enter Coupon Name') }}">
                                <p id="editErr_name" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="">{{ __('Code') . '*' }}</label>
                                <input type="text" id="in_code" class="form-control" name="code"
                                    placeholder="Enter Coupon Code">
                                <p id="editErr_code" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                        </div>
                    </div>

                    <div class="row no-gutters">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="">{{ __('Coupon Type') . '*' }}</label>
                                <select name="type" id="in_type" class="form-control">
                                    <option disabled>{{ __('Select a Type') }}</option>
                                    <option value="fixed">{{ __('Fixed') }}</option>
                                    <option value="percentage">{{ __('Percentage') }}</option>
                                </select>
                                <p id="editErr_type" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="">{{ __('Value') . '*' }}</label>
                                <input type="number" step="0.01" id="in_value" class="form-control" name="value"
                                    placeholder="{{ __('Enter Coupon Value') }}">
                                <p id="editErr_value" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                        </div>
                    </div>

                    <div class="row no-gutters">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="">{{ __('Start Date') . '*' }}</label>
                                <input type="text" id="in_start_date" class="form-control datepicker"
                                    name="start_date" placeholder="{{ __('Enter Start Date') }}">
                                <p id="editErr_start_date" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="">{{ __('End Date') . '*' }}</label>
                                <input type="text" id="in_end_date" class="form-control datepicker" name="end_date"
                                    placeholder="{{ __('Enter End Dat') }}e">
                                <p id="editErr_end_date" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                        </div>
                    </div>

                    <div class="row no-gutters">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="">{{ __('Events') }}</label>
                                <select id="in_events" class="select2" name="events[]" multiple="multiple"
                                    placeholder="Select Events">
                                    @foreach ($events as $event)
                                        <option value="{{ $event->id }}">
                                            {{ optional($event->information)->title ?: __('Event #:id', ['id' => $event->id]) }}
                                            ({{ $event->organizer?->username ?: __('TICA') }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-muted small mt-2">{{ __('Leave blank to apply this coupon to every event.') }}</p>
                                <p id="editErr_events" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">
                    {{ __('Close') }}
                </button>
                <button id="updateBtn" type="button" class="btn btn-sm btn-primary">
                    {{ __('Update') }}
                </button>
            </div>
        </div>
    </div>
</div>
