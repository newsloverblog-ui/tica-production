@php
  $customer = Auth::guard('customer')->user();
  $organizer = Auth::guard('organizer')->user();
  $createEventUrl = $organizer ? route('organizer.add.event.event') : route('organizer.signup');
  $organizerUrl = $organizer ? route('organizer.dashboard') : route('organizer.login');
  $ticketUrl = $customer ? route('customer.booking.my_booking') : route('customer.login');
@endphp
<header class="topbar">
  <div class="wrap nav">
    <a href="{{ route('index') }}" class="logo focus-ring" aria-label="{{ __('TICA home') }}">TICA</a>
    <nav class="nav-links" aria-label="{{ __('Main navigation') }}">
      <a href="{{ route('events') }}" @if(request()->routeIs('events') || request()->routeIs('event.*')) aria-current="page" @endif>{{ __('Discover') }}</a>
      <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>{{ __('How it works') }}</a>
      <a href="{{ route('pricing') }}" @if(request()->routeIs('pricing')) aria-current="page" @endif>{{ __('Pricing') }}</a>
      <a href="{{ $organizerUrl }}" @if(request()->routeIs('organizer.*')) aria-current="page" @endif>{{ __('For organisers') }}</a>
    </nav>
    <div class="nav-actions">
      <a href="{{ $ticketUrl }}" class="ticket-link focus-ring">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 0 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 0 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
        {{ __('My tickets') }}
      </a>
      <a class="nav-create focus-ring" href="{{ $createEventUrl }}">{{ __('Create event') }}</a>
      <button class="menu-toggle focus-ring" type="button" aria-controls="mobile-menu" aria-expanded="false" aria-label="{{ __('Open navigation') }}">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
    <div class="mobile-menu" id="mobile-menu" hidden>
      <div class="mobile-menu-head"><strong>{{ __('Menu') }}</strong><button type="button" class="mobile-menu-close" aria-label="{{ __('Close navigation') }}">×</button></div>
      <nav aria-label="{{ __('Mobile navigation') }}">
        <a href="{{ route('events') }}">{{ __('Discover') }}</a>
        <a href="{{ route('about') }}">{{ __('How it works') }}</a>
        <a href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
        <a href="{{ $organizerUrl }}">{{ __('For organisers') }}</a>
        <a href="{{ $ticketUrl }}">{{ __('My tickets') }}</a>
        @if(!$customer)<a href="{{ route('customer.login') }}">{{ __('Log in') }}</a>@endif
      </nav>
    </div>
  </div>
</header>
<div class="mobile-menu-backdrop" hidden></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var toggle=document.querySelector('.menu-toggle'),menu=document.getElementById('mobile-menu'),close=document.querySelector('.mobile-menu-close'),backdrop=document.querySelector('.mobile-menu-backdrop');
  if(!toggle||!menu||!backdrop)return;
  function setOpen(open){toggle.setAttribute('aria-expanded',String(open));menu.hidden=!open;backdrop.hidden=!open;document.body.classList.toggle('nav-open',open);if(open&&close)close.focus()}
  toggle.addEventListener('click',function(){setOpen(toggle.getAttribute('aria-expanded')!=='true')});
  if(close)close.addEventListener('click',function(){setOpen(false)});backdrop.addEventListener('click',function(){setOpen(false)});
  document.addEventListener('keydown',function(event){if(event.key==='Escape')setOpen(false)});
});
</script>
