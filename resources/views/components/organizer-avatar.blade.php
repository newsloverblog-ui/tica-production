@props(['organizer' => null, 'size' => 40, 'class' => ''])
{{-- Shared organiser avatar: the photo when there is one, otherwise initials. --}}
@php
  $url = $organizer?->avatar_url;
  $initials = $organizer?->avatar_initials ?? 'T';
  $label = $organizer?->verified_legal_name ?: ($organizer?->username ?: 'Organiser');
@endphp
<span {{ $attributes->merge(['class' => trim('tica-avatar ' . $class)]) }}
  style="--av-size: {{ (int) $size }}px">
  @if ($url)
    <img src="{{ $url }}" alt="{{ $label }}" width="{{ (int) $size }}" height="{{ (int) $size }}" loading="lazy" decoding="async"
      onerror="this.remove()">
  @endif
  <span class="tica-avatar__initials" aria-hidden="{{ $url ? 'true' : 'false' }}">{{ $initials }}</span>
</span>
@once
  <style>
    .tica-avatar{position:relative;display:inline-grid;place-items:center;flex:none;width:var(--av-size);height:var(--av-size);border-radius:calc(var(--av-size) * .28);overflow:hidden;background:linear-gradient(135deg,#2D6BD8,#174DA8);color:#fff;font-weight:700;font-size:calc(var(--av-size) * .36);line-height:1;vertical-align:middle}
    .tica-avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1}
  </style>
@endonce
