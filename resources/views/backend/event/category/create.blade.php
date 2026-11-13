<div class="modal fade tcat-modal" id="createModal" tabindex="-1" role="dialog" aria-labelledby="createCategoryTitle"
  aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createCategoryTitle">{{ __('Add a category') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <form id="modalForm" class="modal-form create" action="{{ route('admin.event_management.store_category') }}"
          method="post">
          @csrf

          <div class="tcat-row">
            <div class="form-group">
              <label for="tcat-create-name">{{ __('Name') }}*</label>
              <input type="text" id="tcat-create-name" class="form-control" name="name" placeholder="{{ __('e.g. Concerts') }}">
              <p id="err_name" class="mt-1 mb-0 text-danger em"></p>
            </div>

            <div class="form-group">
              <label for="tcat-create-lang">{{ __('Language') }}*</label>
              <select name="language_id" id="tcat-create-lang" class="form-control">
                @if (count($langs) > 1)
                  <option selected disabled>{{ __('Select a language') }}</option>
                @endif
                @foreach ($langs as $lang)
                  <option value="{{ $lang->id }}" {{ count($langs) === 1 || $lang->id === $language->id ? 'selected' : '' }}>{{ $lang->name }}</option>
                @endforeach
              </select>
              <p id="err_language_id" class="mt-1 mb-0 text-danger em"></p>
            </div>
          </div>

          @include('backend.event.category._icon-picker', ['pickerId' => 'tcat-create-picker', 'errId' => 'err_icon'])

          <div class="tcat-row tcat-row-3">
            <div class="form-group">
              <label for="tcat-create-status">{{ __('Status') }}*</label>
              <select name="status" id="tcat-create-status" class="form-control">
                <option value="1" selected>{{ __('Active') }}</option>
                <option value="0">{{ __('Hidden') }}</option>
              </select>
              <p id="err_status" class="mt-1 mb-0 text-danger em"></p>
            </div>

            <div class="form-group">
              <label for="tcat-create-featured">{{ __('Featured') }}*</label>
              <select name="is_featured" id="tcat-create-featured" class="form-control">
                <option value="no" selected>{{ __('No') }}</option>
                <option value="yes">{{ __('Yes') }}</option>
              </select>
              <p id="err_is_featured" class="mt-1 mb-0 text-danger em"></p>
            </div>

            <div class="form-group">
              <label for="tcat-create-order">{{ __('Order') }}*</label>
              <input type="number" id="tcat-create-order" class="form-control ltr" name="serial_number"
                value="{{ ($categories->max('serial_number') ?? 0) + 1 }}" min="0">
              <p id="err_serial_number" class="mt-1 mb-0 text-danger em"></p>
            </div>
          </div>
          <p class="tcat-help mb-0">{{ __('Lower numbers show first.') }}</p>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">{{ __('Cancel') }}</button>
        <button id="modalSubmit" type="button" class="btn btn-primary">{{ __('Add category') }}</button>
      </div>
    </div>
  </div>
</div>
