@props(['organizer' => null, 'size' => 40, 'class' => ''])
{{-- Shared organiser avatar: the photo when there is one, otherwise initials. Styles: .t-avatar in ds.css --}}
@php
  $url = $organizer?->avatar_url;
  $initials = $organizer?->avatar_initials ?? 'T';
  $label = $organizer?->verified_legal_name ?: ($organizer?->username ?: 'Organiser');
@endphp
<span {{ $attributes->merge(['class' => trim('t-avatar tica-avatar ' . $class)]) }} style="--av: {{ (int) $size }}px">
  <span aria-hidden="{{ $url ? 'true' : 'false' }}">{{ $initials }}</span>
  @if ($url)
    <img src="{{ $url }}" alt="{{ $label }}" width="{{ (int) $size }}" height="{{ (int) $size }}" decoding="async" onerror="this.remove()">
  @endif
</span>
@once
  <style>
    /* Fallback for pages that don't load ds.css (public pages). */
    .tica-avatar{position:relative;display:inline-grid;place-items:center;flex:none;width:var(--av);height:var(--av);border-radius:50%;overflow:hidden;background:#EEF2FD;color:#2E4FD8;font-weight:600;font-size:calc(var(--av) * .38);line-height:1}
    .tica-avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  </style>
@endonce
