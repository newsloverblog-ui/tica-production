{{-- Icon picker for event categories. Expects $icons (config('category_icons')), $pickerId and $errId. --}}
<fieldset class="tcat-picker" id="{{ $pickerId }}">
  <legend class="tcat-label">{{ __('Icon') }}*</legend>
  <p class="tcat-help">{{ __('Shown next to the category on the home page and in Discover.') }}</p>
  <div class="tcat-grid">
    @foreach ($icons as $key => $icon)
      <label class="tcat-opt" title="{{ __($icon['label']) }}">
        <input type="radio" name="icon" value="{{ $key }}" data-label="{{ __($icon['label']) }}">
        <span class="tcat-opt-box">
          <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icon['svg'] !!}</svg>
        </span>
        <span class="sr-only">{{ __($icon['label']) }}</span>
      </label>
    @endforeach
  </div>
  <p class="tcat-picked" aria-live="polite">{{ __('No icon picked yet.') }}</p>
  <p id="{{ $errId }}" class="mt-1 mb-0 text-danger em"></p>
</fieldset>
