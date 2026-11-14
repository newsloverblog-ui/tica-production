@extends('backend.layout')

@section('content')
  @php $tone = $i['status']['tone']; @endphp
  <div class="t-page-head">
    <div>
      <p class="in-crumb"><a href="{{ route('admin.integrations.index') }}">{{ __('Integrations') }}</a></p>
      <h1 class="page-title">{{ $i['name'] }}</h1>
      <p>{{ __($i['summary']) }}</p>
    </div>
    <span class="t-badge{{ $tone ? ' t-badge--' . $tone : '' }} in-status">{{ __($i['status']['label']) }}</span>
  </div>

  @foreach (['success' => 'green', 'warning' => 'orange'] as $flash => $flashTone)
    @if (session($flash))
      <div class="t-card in-note in-note--{{ $flashTone }}" role="status">{{ session($flash) }}</div>
    @endif
  @endforeach
  @foreach ($i['notes'] as $note)
    <div class="t-card in-note in-note--orange" role="note">{{ __($note) }}</div>
  @endforeach
  @if ($i['needs_store'] && !$storeReady)
    <div class="t-card in-note in-note--orange" role="note">{{ __('Settings marked "saved here" need the database update first (database/supabase/tica_database_update.sql). Until then the server variables are used.') }}</div>
  @endif

  <div class="in-layout">
    <form class="t-card in-form" method="POST" action="{{ route('admin.integrations.update', $i['key']) }}" novalidate autocomplete="off">
      @csrf
      @if ($i['toggleable'])
        <label class="in-switch">
          <input type="hidden" name="enabled" value="0">
          <input class="t-switch" type="checkbox" name="enabled" value="1" @checked(old('enabled', $i['enabled']))>
          <span><b>{{ __('Turned on') }}</b><small>{{ __('When off, TICA doesn\'t use this service.') }}</small></span>
        </label>
        @error('enabled')<p class="t-error">{{ $message }}</p>@enderror
      @endif

      @foreach ($i['fields'] as $f)
        @php $name = 'f_' . $f['name']; $id = 'in-' . $f['name']; @endphp
        <div class="t-field">
          @if ($f['type'] === 'toggle')
            <label class="in-switch">
              <input type="hidden" name="{{ $name }}" value="0">
              <input class="t-switch" id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $f['display'] === '1' || ($f['display'] === '' && filter_var(env($f['env'] ?? '', false), FILTER_VALIDATE_BOOL))))>
              <span><b>{{ __($f['label']) }}</b>@if (!empty($f['help']))<small>{{ __($f['help']) }}</small>@endif</span>
            </label>
          @else
            <label class="t-label" for="{{ $id }}">{{ __($f['label']) }}@if (!empty($f['required']))<span class="t-muted"> · {{ __('required') }}</span>@endif</label>
            @if ($f['type'] === 'select')
              <select class="t-select" id="{{ $id }}" name="{{ $name }}">
                @foreach ($f['options'] as $value => $label)
                  <option value="{{ $value }}" @selected((string) old($name, $f['display'] ?: (string) env($f['env'] ?? '', '')) === (string) $value)>{{ __($label) }}</option>
                @endforeach
              </select>
            @elseif ($f['type'] === 'secret')
              <input class="t-input" id="{{ $id }}" type="password" name="{{ $name }}" value="{{ old($name) }}" autocomplete="new-password" spellcheck="false"
                placeholder="{{ $f['set'] ? __('Saved (:hint). Leave blank to keep it.', ['hint' => $f['hint']]) : ($f['env_set'] ? __('Using the server variable :env', ['env' => $f['env']]) : __('Not set')) }}"
                @error($name) aria-invalid="true" @enderror>
            @else
              <input class="t-input" id="{{ $id }}" type="{{ $f['type'] === 'email' ? 'email' : ($f['type'] === 'url' ? 'url' : 'text') }}" name="{{ $name }}" value="{{ old($name, $f['display']) }}" spellcheck="false"
                placeholder="{{ $f['env_set'] && !$f['set'] ? __('Using the server variable :env', ['env' => $f['env']]) : '' }}"
                @error($name) aria-invalid="true" @enderror>
            @endif
            @if (!empty($f['help']))<span class="t-help">{{ __($f['help']) }}</span>@endif
            <span class="in-src">{{ $f['source'] === 'store' ? __('Saved here') . (!empty($f['env']) ? ' · ' . __('overrides :env', ['env' => $f['env']]) : '') : __('Saved here') }}</span>
            @error($name)<span class="t-error">{{ $message }}</span>@enderror
          @endif
        </div>
      @endforeach

      <div class="t-field in-pass">
        <label class="t-label" for="in-pass">{{ __('Your admin password') }}</label>
        <input class="t-input" id="in-pass" type="password" name="current_password" autocomplete="current-password" @error('current_password') aria-invalid="true" @enderror>
        <span class="t-help">{{ __('Only needed when you change a key or secret.') }}</span>
        @error('current_password')<span class="t-error">{{ $message }}</span>@enderror
      </div>

      <div class="in-actions">
        <button class="t-btn t-btn--primary" type="submit">{{ __('Save') }}</button>
      </div>
    </form>

    <aside class="in-side">
      <section class="t-card">
        <h2 class="t-card__title">{{ __('Test the connection') }}</h2>
        <p class="t-help">
          @if ($i['tested_at'])
            {{ $i['test_ok'] ? __('Passed') : __('Failed') }} {{ \Carbon\Carbon::parse($i['tested_at'])->diffForHumans() }}: {{ $i['test_message'] }}
          @else
            {{ __('Not tested since the last change.') }}
          @endif
        </p>
        @if ($i['apply_after_test'])
          <p class="t-help">{{ __('For safety, saved settings only take effect after a passing test.') }}</p>
        @endif
        <form method="POST" action="{{ route('admin.integrations.test', $i['key']) }}">
          @csrf
          <button class="t-btn t-btn--secondary t-btn--block" type="submit">{{ $i['key'] === 'email' ? __('Send me a test email') : __('Test connection') }}</button>
        </form>
        @if ($i['generate'] === 'vapid')
          <form method="POST" action="{{ route('admin.integrations.generate', $i['key']) }}" class="in-gen">
            @csrf
            <button class="t-btn t-btn--ghost t-btn--block" type="submit">{{ __('Generate new keys') }}</button>
          </form>
        @endif
      </section>

      @if ($i['urls'])
        <section class="t-card">
          <h2 class="t-card__title">{{ __('Copy into the provider') }}</h2>
          @foreach ($i['urls'] as $u)
            <div class="t-field in-copy">
              <span class="t-label">{{ __($u['label']) }}</span>
              <span class="in-copy__row"><input class="t-input" type="text" value="{{ $u['value'] }}" readonly aria-label="{{ __($u['label']) }}"><button class="t-btn t-btn--secondary t-btn--sm" type="button" data-copy-value="{{ $u['value'] }}">{{ __('Copy') }}</button></span>
            </div>
          @endforeach
        </section>
      @endif
    </aside>
  </div>

  <script>
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-copy-value]');
      if (!b) return;
      var done = function () { var t = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = t; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copyValue).then(done, function () { b.previousElementSibling.select(); });
      else { b.previousElementSibling.select(); }
    });
  </script>
@endsection
