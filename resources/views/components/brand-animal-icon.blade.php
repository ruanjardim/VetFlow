@props(['icon' => 'generic'])

<span class="brand-animal-icon" data-brand-icon="{{ $icon }}" role="img" aria-label="{{ \App\Modules\Clinics\Services\ClinicBrandingService::icons()[$icon] ?? 'Pata animal' }}">
  @switch($icon)
    @case('feline')
      <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="11" cy="15" r="5"/><circle cx="21" cy="9" r="5"/><circle cx="32" cy="10" r="5"/><circle cx="39" cy="19" r="5"/><path d="M15 35c0-8 5-15 12-15 8 0 14 8 14 15 0 6-5 9-11 7-3-1-5-1-8 0-5 2-7-2-7-7Z"/></svg>
      @break
    @case('equine')
      <svg viewBox="0 0 48 48" aria-hidden="true"><path fill-rule="evenodd" d="M9 8h8v11c0 7 3 12 7 12s7-5 7-12V8h8v11c0 13-6 21-15 21S9 32 9 19V8Zm8 0h14v7H17V8Z" clip-rule="evenodd"/></svg>
      @break
    @case('avian')
      <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M23 6h4v21l10-11 3 3-12 13 13 1v4l-15-1-7 9-3-2 5-9L7 39l-1-4 15-5L10 20l3-3 10 9V6Z"/></svg>
      @break
    @case('exotic')
      <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M23 7h4v13l7-8 3 3-9 10 12-3 1 4-12 3 10 6-2 4-11-7-1 12h-4l1-13-11 8-2-4 10-7-13-1 1-4 13 1-9-9 3-3 9 9V7Z"/></svg>
      @break
    @case('canine')
      <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="10" cy="17" r="6"/><circle cx="20" cy="9" r="6"/><circle cx="32" cy="10" r="6"/><circle cx="40" cy="20" r="6"/><path d="M14 36c0-8 6-15 13-15 8 0 15 8 15 15 0 6-5 9-11 7-3-1-6-1-9 0-5 2-8-1-8-7Z"/></svg>
      @break
    @default
      <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="10" cy="16" r="5"/><circle cx="20" cy="9" r="5"/><circle cx="31" cy="9" r="5"/><circle cx="39" cy="17" r="5"/><path d="M14 35c0-8 6-14 13-14 8 0 14 7 14 14 0 6-5 9-11 7-3-1-5-1-8 0-5 2-8-1-8-7Z"/></svg>
  @endswitch
</span>
