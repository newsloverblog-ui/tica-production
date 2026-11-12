<div class="ta-save tw-save">
  {{-- Save comes first so pressing Enter in a field saves rather than resets. --}}
  <button class="btn btn-primary" type="submit">{{ __('Save changes') }}</button>
  <button class="tw-reset" type="submit" name="reset" value="1" formnovalidate onclick="return confirm(@js(__('Put this section back to the original TICA design content? Your changes here will be replaced.')))">{{ __('Use the original design') }}</button>
</div>
