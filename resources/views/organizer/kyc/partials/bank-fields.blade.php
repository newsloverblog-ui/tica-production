<div class="kv-bank">
  <div class="{{ empty($compact) ? 'kv-two' : '' }}">
    <div class="kv-f">
      <label for="{{ empty($compact) ? 'kv-bk' : 'kv-bk2' }}">{{ __('Bank') }}</label>
      <select id="{{ empty($compact) ? 'kv-bk' : 'kv-bk2' }}" name="bank_code">
        <option value="">{{ __('Choose your bank') }}</option>
        @foreach ($banks as $bank)
          <option value="{{ $bank['code'] }}" @selected(old('bank_code') === (string) $bank['code'])>{{ $bank['name'] }}</option>
        @endforeach
      </select>
      @error('bank_code')<p class="kv-err">{{ $message }}</p>@enderror
    </div>
    <div class="kv-f">
      <label for="{{ empty($compact) ? 'kv-no' : 'kv-no2' }}">{{ __('Account number') }}</label>
      <input id="{{ empty($compact) ? 'kv-no' : 'kv-no2' }}" name="account_number" type="text" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="{{ __('10 digits') }}" value="{{ old('account_number') }}">
      @error('account_number')<p class="kv-err">{{ $message }}</p>@enderror
    </div>
  </div>
  <div class="kv-res" aria-live="polite"></div>
</div>
