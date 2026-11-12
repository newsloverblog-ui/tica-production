<footer class="ft">
  <div class="wrap ft-grid">
    <div><div class="ft-logo">TICA</div><p class="ft-copy">{{ __('The home of live experiences, built for the people creating culture across Nigeria.') }}</p></div>
    <div><h2>{{ __('Explore') }}</h2><ul><li><a href="{{ route('events') }}">{{ __('Discover events') }}</a></li><li><a href="{{ Auth::guard('customer')->check() ? route('customer.booking.my_booking') : route('customer.login') }}">{{ __('My tickets') }}</a></li><li><a href="{{ route('about') }}">{{ __('How it works') }}</a></li></ul></div>
    <div><h2>{{ __('Organisers') }}</h2><ul><li><a href="{{ route('organizer.signup') }}">{{ __('Create an event') }}</a></li><li><a href="{{ route('pricing') }}">{{ __('Pricing') }}</a></li><li><a href="{{ Auth::guard('organizer')->check() ? route('organizer.dashboard') : route('organizer.login') }}">{{ __('Dashboard') }}</a></li></ul></div>
    <div><h2>{{ __('TICA') }}</h2><ul><li><a href="{{ route('about') }}">{{ __('About') }}</a></li><li><a href="{{ route('contact') }}">{{ __('Contact') }}</a></li>@foreach($quickLinkInfos->take(1) as $quickLinkInfo)<li><a href="{{ $quickLinkInfo->url }}">{{ __('Terms and policies') }}</a></li>@endforeach</ul></div>
  </div>
  <div class="ft-base"><div class="wrap"><span>© {{ date('Y') }} TICA Technologies Limited</span><span>{{ __('Made for the culture in Lagos') }}</span></div></div>
</footer>
