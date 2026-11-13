<div class="modal fade tcat-modal" id="editEventCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editCategoryTitle">{{ __('Edit category') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <form id="ajaxEditForm" class="modal-form" action="{{ route('admin.event_management.update_category') }}" method="post">
          @method('PUT')
          @csrf
          <input type="hidden" id="in_id" name="id">

          <div class="form-group">
            <label for="in_name">{{ __('Name') }}*</label>
            <input type="text" id="in_name" class="form-control" name="name">
            <p id="editErr_name" class="mt-1 mb-0 text-danger em"></p>
          </div>

          @include('backend.event.category._icon-picker', ['pickerId' => 'tcat-edit-picker', 'errId' => 'editErr_icon'])

          <div class="tcat-row tcat-row-3">
            <div class="form-group">
              <label for="in_status">{{ __('Status') }}*</label>
              <select name="status" id="in_status" class="form-control">
                <option value="1">{{ __('Active') }}</option>
                <option value="0">{{ __('Hidden') }}</option>
              </select>
              <p id="editErr_status" class="mt-1 mb-0 text-danger em"></p>
            </div>

            <div class="form-group">
              <label for="in_is_featured">{{ __('Featured') }}*</label>
              <select name="is_featured" id="in_is_featured" class="form-control">
                <option value="no">{{ __('No') }}</option>
                <option value="yes">{{ __('Yes') }}</option>
              </select>
              <p id="editErr_is_featured" class="mt-1 mb-0 text-danger em"></p>
            </div>

            <div class="form-group">
              <label for="in_serial_number">{{ __('Order') }}*</label>
              <input type="number" id="in_serial_number" class="form-control ltr" name="serial_number" min="0">
              <p id="editErr_serial_number" class="mt-1 mb-0 text-danger em"></p>
            </div>
          </div>
          <p class="tcat-help mb-0">{{ __('Lower numbers show first.') }}</p>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">{{ __('Cancel') }}</button>
        <button id="updateBtn" type="button" class="btn btn-primary">{{ __('Save changes') }}</button>
      </div>
    </div>
  </div>
</div>
