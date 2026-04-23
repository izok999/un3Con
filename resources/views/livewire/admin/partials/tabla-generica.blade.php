@props(['datos' => [], 'vacio' => 'Sin datos disponibles'])

@if(count($datos) === 0)
    <p class="text-base-content/50 text-center py-6">{{ $vacio }}</p>
@else
    <div class="overflow-x-auto">
        <table class="table table-zebra table-sm">
            <thead>
                <tr>
                    @foreach(array_keys((array) $datos[0]) as $col)
                        <th class="text-xs font-mono whitespace-nowrap">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($datos as $fila)
                    <tr>
                        @foreach((array) $fila as $valor)
                            <td class="text-sm whitespace-nowrap max-w-xs truncate" title="{{ $valor }}">
                                {{ $valor ?? '—' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-base-content/40 mt-2">{{ count($datos) }} registro(s)</p>
@endif
