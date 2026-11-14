@extends('backend.layout')

@section('content')
  @php $s = $status; @endphp
  <div class="t-page-head">
    <div>
      <h1 class="page-title">{{ __('Database updates') }}</h1>
      <p>{{ __('New versions of TICA sometimes need new tables or columns. Apply them here after uploading new files. Works on cPanel (MySQL/MariaDB) and Supabase (Postgres).') }}</p>
    </div>
    <span class="t-badge {{ $s['ok'] && !$s['pending'] ? 't-badge--green' : 't-badge--orange' }} in-status">
      {{ !$s['ok'] ? __('Can\'t read the database') : ($s['pending'] ? trans_choice(':count update waiting|:count updates waiting', count($s['pending'])) : __('Up to date')) }}
    </span>
  </div>

  @foreach (['success' => 'green', 'warning' => 'orange'] as $flash => $tone)
    @if (session($flash))<div class="t-card in-note in-note--{{ $tone }}" role="status">{{ session($flash) }}</div>@endif
  @endforeach

  <div class="in-layout">
    <section class="t-card">
      @if (!$s['ok'])
        <h2 class="t-card__title">{{ __('The database could not be read') }}</h2>
        <p class="t-help">{{ __('Check the database name, user and password in the .env file.') }}</p>
        <pre class="db-out">{{ $s['error'] }}</pre>
      @elseif (!$s['pending'])
        <h2 class="t-card__title">{{ __('Everything is in place') }}</h2>
        <p class="t-help">{{ __(':applied of :total updates applied on this :driver database.', ['applied' => $s['applied'], 'total' => $s['total'], 'driver' => $s['driver'] === 'pgsql' ? 'Postgres' : 'MySQL/MariaDB']) }}</p>
      @else
        <h2 class="t-card__title">{{ trans_choice(':count update waiting|:count updates waiting', count($s['pending'])) }}</h2>
        <p class="t-help">{{ __(':applied of :total already applied on this :driver database. Updates add tables and columns and convert data where needed; they don\'t delete your events, orders or accounts.', ['applied' => $s['applied'], 'total' => $s['total'], 'driver' => $s['driver'] === 'pgsql' ? 'Postgres' : 'MySQL/MariaDB']) }}</p>
        <details class="db-list"><summary>{{ __('Show the list') }}</summary>
          <ol>@foreach ($s['pending'] as $name)<li><code>{{ $name }}</code></li>@endforeach</ol>
        </details>
      @endif
      @if (session('db_output'))
        <h3 class="st-sub">{{ __('What happened') }}</h3>
        <pre class="db-out">{{ session('db_output') }}</pre>
      @endif
    </section>

    @if ($s['ok'] && $s['pending'])
      <aside class="in-side">
        <form class="t-card" method="POST" action="{{ route('admin.database.apply') }}">
          @csrf
          <h2 class="t-card__title">{{ __('Apply updates') }}</h2>
          <p class="t-help">{{ __('First download a backup (cPanel: phpMyAdmin → Export; Supabase: Database → Backups). It usually takes under a minute.') }}</p>
          <div class="t-field">
            <label class="t-label" for="db-pass">{{ __('Your admin password') }}</label>
            <input class="t-input" id="db-pass" type="password" name="current_password" autocomplete="current-password" required @error('current_password') aria-invalid="true" @enderror>
            @error('current_password')<span class="t-error">{{ $message }}</span>@enderror
          </div>
          <button class="t-btn t-btn--primary t-btn--block" type="submit" onclick="this.disabled=true;this.textContent='Applying…';this.form.submit()">{{ __('Apply :count updates', ['count' => count($s['pending'])]) }}</button>
        </form>
      </aside>
    @endif
  </div>
@endsection
