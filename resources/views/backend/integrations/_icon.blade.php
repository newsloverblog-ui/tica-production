@php($icon = $icon ?? 'link')
<span class="in-provider-icon" aria-hidden="true">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    @switch($icon)
      @case('payments')
        <rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 9h18M7 15h4"/>
        @break
      @case('email')
        <rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>
        @break
      @case('google')
        <path d="M20 12.2c0-.7-.1-1.4-.2-2H12v3.9h4.5a4.7 4.7 0 0 1-2 3.1v2.6h3.3c2-1.8 3.2-4.5 3.2-7.6Z"/><path d="M12 21.5a9.5 9.5 0 1 1 6.7-16.2"/>
        @break
      @case('notifications')
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
        @break
      @default
        <path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"/>
    @endswitch
  </svg>
</span>
