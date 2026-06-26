{{-- Anexo fotográfico por partida (cotización de venta) --}}
@php
    $detallesConImagenes = $doc->detalles->filter(fn ($d) => $d->tieneImagenes());
@endphp

@if($detallesConImagenes->isNotEmpty())
    @foreach($detallesConImagenes as $detalle)
        @php
            $numPartida = ($detalle->orden ?? $loop->index) + 1;
            $codigoPartida = ($detalle->codigo === '-' || $detalle->codigo === 'MANUAL') ? '—' : ($detalle->codigo ?? '—');
        @endphp
        <div style="page-break-before: always; padding-top: 12px;">
            <div style="font-size: 11pt; font-weight: bold; color: #0B3C5D; margin-bottom: 10px; border-bottom: 2px solid #0B3C5D; padding-bottom: 6px;">
                ANEXO FOTOGRÁFICO — Partida {{ $numPartida }}
            </div>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px; font-size: 9pt;">
                <tr>
                    <td width="18%"><strong>Código:</strong></td>
                    <td>{{ $codigoPartida }}</td>
                </tr>
                <tr>
                    <td valign="top"><strong>Descripción:</strong></td>
                    <td>{{ $detalle->descripcion }}</td>
                </tr>
                <tr>
                    <td><strong>Cantidad:</strong></td>
                    <td>{{ number_format($detalle->cantidad, 2) }} {{ $detalle->unidad ?? $detalle->producto?->unidad ?? 'PZA' }}</td>
                </tr>
            </table>

            @foreach($detalle->rutasImagenes() as $imgIdx => $imgPath)
                @php
                    $fullPath = storage_path('app/public/'.ltrim($imgPath, '/'));
                    $imgDataUri = null;
                    if (file_exists($fullPath)) {
                        $mime = mime_content_type($fullPath) ?: 'image/jpeg';
                        $imgDataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullPath));
                    }
                @endphp
                @if($imgDataUri)
                    <div style="margin-bottom: 14px; text-align: center;">
                        <div style="font-size: 8pt; color: #64748B; margin-bottom: 6px;">
                            Imagen {{ $imgIdx + 1 }} de {{ count($detalle->rutasImagenes()) }}
                        </div>
                        <img src="{{ $imgDataUri }}" alt="Partida {{ $numPartida }}"
                             style="max-width: 100%; max-height: 520px; display: block; margin: 0 auto; border: 1px solid #E2E8F0; border-radius: 4px;">
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
@endif
