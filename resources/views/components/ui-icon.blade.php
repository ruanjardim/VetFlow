@props(['name', 'size' => 20])

<span {{ $attributes->class('ui-icon') }} aria-hidden="true">
  @switch($name)
    @case('dashboard')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
      @break
    @case('clinical')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M9 4h6v3h3v6h-3v3H9v-3H6V7h3V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M5 20h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('calendar')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><rect x="3" y="5" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4M16 3v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('grooming')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M8.5 13.5 4 18a2.1 2.1 0 0 0 3 3l4.5-4.5M15.5 10.5 20 6a2.1 2.1 0 0 0-3-3l-4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m9 8 7 7M14 9l1-5M10 15l-5 1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('sales')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M3 5h2l2.2 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L20.5 9H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.2" fill="currentColor"/><circle cx="18" cy="20" r="1.2" fill="currentColor"/></svg>
      @break
    @case('inventory')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="m4 8 8-4 8 4-8 4-8-4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4 8v8l8 4 8-4V8M12 12v8" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
      @break
    @case('finance')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M12 3v18M16.5 7H9.8a3 3 0 0 0 0 6h4.4a3 3 0 1 1 0 6H7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('catalog')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M5 4h11a3 3 0 0 1 3 3v13H7a2 2 0 0 1-2-2V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M5 17.5A2.5 2.5 0 0 1 7.5 15H19M9 8h6M9 11h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('settings')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
      @break
    @case('users')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0M16 6a3 3 0 0 1 0 6M16.5 15a4.5 4.5 0 0 1 4 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('paw')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><ellipse cx="12" cy="16.5" rx="5.2" ry="3.8" stroke="currentColor" stroke-width="1.8"/><circle cx="6.5" cy="10.5" r="2" stroke="currentColor" stroke-width="1.8"/><circle cx="10.5" cy="6.5" r="2" stroke="currentColor" stroke-width="1.8"/><circle cx="17.5" cy="10.5" r="2" stroke="currentColor" stroke-width="1.8"/><circle cx="14.5" cy="6.5" r="2" stroke="currentColor" stroke-width="1.8"/></svg>
      @break
    @case('money')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M7 9H5v2M17 15h2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('clipboard')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><rect x="5" y="4" width="14" height="17" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M9 4.5V3h6v1.5M8.5 10h7M8.5 14h7M8.5 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @break
    @case('warning')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M10.3 4.2 2.9 17a2 2 0 0 0 1.7 3h14.8a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      @break
    @case('sparkles')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="m12 3 1.2 4.1L17 9l-3.8 1.9L12 15l-1.2-4.1L7 9l3.8-1.9L12 3ZM19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14ZM5 14l.7 1.8 1.8.7-1.8.7L5 19l-.7-1.8-1.8-.7 1.8-.7L5 14Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
      @break
    @case('plus')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      @break
    @case('menu')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      @break
    @case('close')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      @break
    @case('chevron')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="m9 7 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      @break
    @case('arrow')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M5 12h14M14 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      @break
    @case('logout')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M9 12h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      @break
    @case('building')
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><path d="M4 21V5l8-3 8 3v16M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      @break
    @default
      <svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/></svg>
  @endswitch
</span>
