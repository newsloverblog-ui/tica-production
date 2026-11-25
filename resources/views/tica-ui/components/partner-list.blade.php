@php
  $tsPartnerItems = array_values(array_filter($partners ?? [], fn ($partner) => !empty($partner['short']) || !empty($partner['name']) || !empty($partner['logo'])));
  if (!$tsPartnerItems) {
      $tsPartnerItems = [
          ['short' => 'COH', 'name' => "Creative\nOpen House", 'logo' => ''],
          ['short' => 'NDFA', 'name' => "Nollywood DIC\nFilm Academy", 'logo' => ''],
      ];
  }
  $tsPartnerImage = fn ($path) => \App\Support\TicaSite::imageUrl($path);
@endphp
<ul class="partner-list" role="list" aria-label="{{ __('TICA partners') }}">
  @foreach ($tsPartnerItems as $tsPartnerIndex => $tsPartner)
    @php($tsPartnerName = $tsPartner['name'] ?? '')
    @php($tsPartnerShort = $tsPartner['short'] ?? '')
    @if (!empty($tsPartner['logo']))
      <li class="partner-logo has-img">
        <img data-src="{{ $tsPartnerImage($tsPartner['logo']) }}" alt="{{ $tsPartnerName ?: $tsPartnerShort }}" loading="lazy" decoding="async">
      </li>
    @else
      <li class="partner-logo {{ ['coh', 'ndfa'][$tsPartnerIndex % 2] }}">
        @if ($tsPartnerShort)<b @if ($tsPartnerName) aria-hidden="true" @endif>{{ $tsPartnerShort }}</b>@endif
        @if ($tsPartnerName)<small>{!! nl2br(e($tsPartnerName), false) !!}</small>@endif
      </li>
    @endif
  @endforeach
</ul>
