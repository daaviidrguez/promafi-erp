{{--
  Encabezado PDF alineado con estado de cuenta / cobranza: nombre comercial (58%), logo derecha (42%, max 45px).
  @param \App\Models\Empresa|null $empresa  Si es null se usa Empresa::principal().
  @param string|null $titulo  Texto opcional debajo del renglón de empresa (ej. "Estado de Cuenta").
--}}
@php
    $empresaHeader = $empresa ?? \App\Models\Empresa::principal();
    if (! $empresaHeader) {
        $empresaHeader = (object) ['nombre_comercial' => '', 'razon_social' => 'EMPRESA', 'logo_path' => null];
    }
    $logoDataUri = null;
    if ($empresaHeader && ($empresaHeader->logo_path ?? null)) {
        $logoPath = storage_path('app/public/'.$empresaHeader->logo_path);
        if (! file_exists($logoPath)) {
            $logoPath = public_path('storage/'.$empresaHeader->logo_path);
        }
        if ($logoPath && file_exists($logoPath)) {
            $logoDataUri = 'data:'.mime_content_type($logoPath).';base64,'.base64_encode(file_get_contents($logoPath));
        }
    }
@endphp
<div class="pdf-header-empresa-logo" style="border-bottom: 3px solid #0B3C5D; padding-bottom: 10px; margin-bottom: 15px;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="58%" valign="middle" style="padding-right: 12px;">
                <div style="font-size: 10.5pt; font-weight: bold; color: #0B3C5D;">
                    {{ strtoupper($empresaHeader->nombre_comercial ?? $empresaHeader->razon_social ?? 'PROMAFI - SOLUCIONES INDUSTRIALES') }}
                </div>
            </td>
            <td width="42%" valign="middle" style="text-align: right;">
                @if($logoDataUri)
                    <img src="{{ $logoDataUri }}" alt="" style="max-height: 45px; display: block; margin-left: auto;">
                @endif
            </td>
        </tr>
    </table>
    @if(! empty($titulo))
        <div style="margin-top: 8px; font-size: 10pt;">
            {{ $titulo }}
        </div>
    @endif
</div>
