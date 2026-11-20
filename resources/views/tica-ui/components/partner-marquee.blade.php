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
<div class="partner-marquee" aria-label="TICA partners">
  <div class="partner-track">
    @foreach ([0, 1] as $tsPartnerCopy)
      <div class="partner-group" @if ($tsPartnerCopy === 1) aria-hidden="true" @endif>
        @foreach ([0, 1, 2] as $tsPartnerRepeat)
          @foreach ($tsPartnerItems as $tsPartnerIndex => $tsPartner)
            @php $tsPartnerLabel = trim(($tsPartner['short'] ?? '') . ' ' . str_replace("\n", ' ', $tsPartner['name'] ?? '')); @endphp
            @if (!empty($tsPartner['logo']))
              <span class="partner-logo has-img" aria-label="{{ $tsPartnerLabel }}" @if ($tsPartnerCopy || $tsPartnerRepeat) aria-hidden="true" @endif>
                <img data-src="{{ $tsPartnerImage($tsPartner['logo']) }}" alt="" loading="lazy" decoding="async">
              </span>
            @else
              <span class="partner-logo {{ ['coh', 'ndfa'][$tsPartnerIndex % 2] }}" aria-label="{{ $tsPartnerLabel }}" @if ($tsPartnerCopy || $tsPartnerRepeat) aria-hidden="true" @endif>
                <b>{{ $tsPartner['short'] }}</b><small>{!! nl2br(e($tsPartner['name']), false) !!}</small>
              </span>
            @endif
          @endforeach
        @endforeach
      </div>
    @endforeach
  </div>
</div>
