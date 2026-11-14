{{-- Shown on the older settings pages: the same settings now also live in Admin > Integrations. --}}
@if (is_null($roleInfo ?? null))
  <div class="t-card in-note in-note--green" role="note">
    {{ __('These settings are also in') }} <a href="{{ route('admin.integrations.index') }}">{{ __('Integrations') }}</a>{{ __(', with a connection test and plain-language help. Both pages save to the same place.') }}
  </div>
@endif
