@verbatim
<a class="skip" href="#main">Skip to content</a>

<div class="topbar">
  <div class="wrap">
    <header class="nav site-nav">
      <a class="logo" href="/" aria-label="TICA home"><img src="/assets/tica-ui/img/tica-logo.png" alt="TICA"></a>
      <nav class="nav-links" aria-label="Main">
@endverbatim
@foreach (\App\Support\TicaSite::get('menu') as $tsLink)
        <a href="{{ $tsLink['url'] }}" @if (str_starts_with($tsLink['url'], '/')) data-nav="{{ trim(explode('?', explode('#', $tsLink['url'])[0])[0], '/') ?: 'home' }}" @else target="_blank" rel="noopener" @endif>{{ $tsLink['label'] }}</a>
@endforeach
@verbatim
      </nav>
      <div class="nav-actions">
        <a class="nav-ic" href="/discover" aria-label="Search events"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.500"/><path d="M16 16l4 4"/></svg></a>
        <a class="login mytix" href="/tickets">My tickets</a>
        <a class="login org-only" href="/dashboard">Dashboard</a>
        <a class="login" href="/login">Log in</a>
        <a class="btn btn-hot" href="/create">Create an event</a>
        <details class="menu">
          <summary aria-label="Menu"><svg class="i-o" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg><svg class="i-x" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg><span class="m-txt">Menu</span></summary>
          <nav aria-label="Mobile">
@endverbatim
@foreach (\App\Support\TicaSite::get('menu') as $tsLink)
            <a href="{{ $tsLink['url'] }}" @unless (str_starts_with($tsLink['url'], '/')) target="_blank" rel="noopener" @endunless>{{ $tsLink['label'] }}</a>
@endforeach
@verbatim
            <a href="/tickets">My tickets</a>
            <a class="org-only" href="/dashboard">Dashboard</a>
            <div class="m-cta"><a class="btn btn-hot" href="/create">Create an event</a><a class="btn btn-line m-login" href="/login">Log in</a></div>
          </nav>
        </details>
      </div>
    </header>
    <header class="nav workspace-nav" aria-label="Dashboard toolbar">
      <div class="workspace-brand-zone">
        <a class="logo" href="/" aria-label="TICA home"><img src="/assets/tica-ui/img/tica-logo.png" alt="TICA"></a>
        <button class="workspace-icon workspace-menu" id="workspace-menu" type="button" aria-label="Open dashboard menu" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
      </div>
      <div class="workspace-context">
        <span class="workspace-badge">TICA</span>
        <strong id="workspace-role">Workspace</strong>
        <span class="vh" id="workspace-title">Dashboard</span>
        <a class="workspace-site" href="/">View website <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 13v6H5V6h6"/></svg></a>
      </div>
      <div class="workspace-actions">
        <a class="workspace-avatar" id="workspace-account" href="/account" aria-label="Open account">T</a>
      </div>
    </header>
  </div>
</div>
@endverbatim
