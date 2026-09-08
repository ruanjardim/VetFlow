@php
  $brandModes = $brandModes ?? \App\Modules\Clinics\Services\ClinicBrandingService::modes();
  $brandIcons = $brandIcons ?? \App\Modules\Clinics\Services\ClinicBrandingService::icons();
  $selectedBrandMode = old('brand_icon_mode', $clinic?->brand_icon_mode ?? 'automatic');
  $selectedBrandIcon = old('brand_icon_key', $clinic?->brand_icon_key ?? 'generic');
@endphp

<div class="field full clinic-branding-fields">
  <div>
    <label for="brand_icon_mode">Ícone ao lado do VetFlow</label>
    <select id="brand_icon_mode" name="brand_icon_mode">
      @foreach($brandModes as $mode => $label)
        <option value="{{ $mode }}" @selected($selectedBrandMode === $mode)>{{ $label }}</option>
      @endforeach
    </select>
    <small>No automático, uma única espécie de atuação define o símbolo; combinações usam a pata genérica.</small>
  </div>

  <fieldset class="brand-icon-picker">
    <legend>Ícone personalizado</legend>
    <div class="brand-icon-options">
      @foreach($brandIcons as $icon => $label)
        <label class="brand-icon-option">
          <input type="radio" name="brand_icon_key" value="{{ $icon }}" @checked($selectedBrandIcon === $icon)>
          <x-brand-animal-icon :icon="$icon" />
          <span>{{ $label }}</span>
        </label>
      @endforeach
    </div>
  </fieldset>
</div>
