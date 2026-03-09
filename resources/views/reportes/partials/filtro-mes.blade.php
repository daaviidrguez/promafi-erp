<form method="GET" action="{{ $action }}" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
    <select name="mes" class="form-control" style="width: auto;">
        @foreach([
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ] as $m => $nombre)
            <option value="{{ $m }}" {{ ($mes ?? now()->month) == $m ? 'selected' : '' }}>{{ $nombre }}</option>
        @endforeach
    </select>
    <select name="año" class="form-control" style="width: auto;">
        @for($y = now()->year; $y >= now()->year - 5; $y--)
            <option value="{{ $y }}" {{ ($año ?? now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
    </select>
    <button type="submit" class="btn btn-primary">Filtrar</button>
</form>
