<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Dia</th>
        <th>Atende</th>
        <th>Abertura</th>
        <th>Início intervalo</th>
        <th>Fim intervalo</th>
        <th>Fechamento</th>
      </tr>
    </thead>
    <tbody>
      @foreach($dayLabels as $weekday => $label)
        @php($definition = $schedule['days'][(string) $weekday])
        <tr>
          <td><strong>{{ $label }}</strong></td>
          <td>
            <label>
              <input type="hidden" name="days[{{ $weekday }}][enabled]" value="0">
              <input type="checkbox" name="days[{{ $weekday }}][enabled]" value="1" @checked(old("days.$weekday.enabled", $definition['enabled']))>
              Aberto
            </label>
          </td>
          <td><input type="time" name="days[{{ $weekday }}][opens_at]" value="{{ old("days.$weekday.opens_at", $definition['opens_at']) }}"></td>
          <td><input type="time" name="days[{{ $weekday }}][break_start]" value="{{ old("days.$weekday.break_start", $definition['break_start']) }}"></td>
          <td><input type="time" name="days[{{ $weekday }}][break_end]" value="{{ old("days.$weekday.break_end", $definition['break_end']) }}"></td>
          <td><input type="time" name="days[{{ $weekday }}][closes_at]" value="{{ old("days.$weekday.closes_at", $definition['closes_at']) }}"></td>
        </tr>
        @error("days.$weekday")<tr><td colspan="6"><span class="field-error">{{ $message }}</span></td></tr>@enderror
      @endforeach
    </tbody>
  </table>
</div>
