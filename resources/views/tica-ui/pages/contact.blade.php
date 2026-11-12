@verbatim
<section class="page" data-page="contact">
  <div class="wrap pagehead">
    <h1 class="display">Talk to us</h1>
    <p class="lead">Questions about a ticket, or planning an event? We're happy to help.</p>
    <div class="fcards">
@endverbatim
@php
  $tsContact = \App\Support\TicaSite::get('contact');
  $tsIcons = [
    'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8z"/>',
    'tiktok' => '<path d="M14 4v10.500a3.500 3.500 0 1 1-3.500-3.500M14 4c.4 2.600 2.200 4.400 5 4.600"/>',
    'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.200" cy="6.800" r=".8"/>',
    'x' => '<path d="M4 4l16 16M20 4L4 20"/>',
    'youtube' => '<rect x="2.500" y="5.500" width="19" height="13" rx="3.500"/><path d="M10 9.500v5l4.500-2.500z"/>',
    'linkedin' => '<rect x="3.500" y="3.500" width="17" height="17" rx="2.500"/><path d="M8 10.500V16M8 7.600v.1M11.500 16v-3.200a2.300 2.300 0 0 1 4.600 0V16M11.500 10.500V16"/>',
    'whatsapp' => '<path d="M12 3.500a8.400 8.400 0 0 0-7.200 12.700L3.600 20.500l4.400-1.150A8.400 8.400 0 1 0 12 3.500z"/><path d="M9 8.500c0 3.500 3 6.500 6.500 6.500l1-1.500-2-1-1 1c-1-.4-2.100-1.500-2.500-2.500l1-1-1-2z"/>',
  ];
@endphp
      @if ($tsContact['phone'])<div class="fcard"><span>Call us</span><h3><a class="ct-tel" href="tel:{{ \App\Support\TicaSite::tel($tsContact['phone']) }}">{{ $tsContact['phone'] }}</a></h3>@if ($tsContact['phone_hours'])<p class="ct-sub">{{ $tsContact['phone_hours'] }}</p>@endif<a class="ct-faq" href="/how#faq">Read the FAQ</a></div>@endif
      @if ($tsContact['emails'])<div class="fcard soc"><span>Email us</span><ul>@foreach ($tsContact['emails'] as $tsMail)<li><a href="mailto:{{ $tsMail['address'] }}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg><b>{{ $tsMail['address'] }}</b>@if ($tsMail['label'])<em>{{ $tsMail['label'] }}</em>@endif</a></li>@endforeach</ul></div>@endif
      @if ($tsContact['socials'])<div class="fcard soc"><span>Follow us</span><ul>@foreach ($tsContact['socials'] as $tsSocial)<li><a href="{{ $tsSocial['url'] }}" target="_blank" rel="noopener" data-handle="{{ \App\Support\TicaSite::NETWORKS[$tsSocial['network']] ?? '' }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $tsIcons[$tsSocial['network']] ?? '' !!}</svg><b>{{ \App\Support\TicaSite::NETWORKS[$tsSocial['network']] ?? '' }}</b>@if ($tsSocial['handle'])<em>{{ $tsSocial['handle'] }}</em>@endif</a></li>@endforeach</ul></div>@endif
@verbatim
    </div>
  </div>
</section>

<!-- JOIN -->
@endverbatim
