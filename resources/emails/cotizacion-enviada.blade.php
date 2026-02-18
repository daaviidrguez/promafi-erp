<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización {{ $cotizacion->folio }}</title>
    {{-- UBICACIÓN: resources/views/emails/cotizacion-enviada.blade.php --}}
</head>
<body style="margin: 0; padding: 0; background-color: #F9FAFB; font-family: Arial, Helvetica, sans-serif;">
    
    <!-- Contenedor Principal -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #F9FAFB; padding: 20px;">
        <tr>
            <td align="center">
                
                <!-- Contenedor de Email -->
                <table width="600" cellpadding="0" cellspacing="0" style="background: #FFFFFF; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    
                    <!-- Header con Logo -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0B3C5D 0%, #1F5F8B 100%); padding: 30px; text-align: center;">
                            @if($empresa->logo)
                            <img src="{{ asset('storage/' . $empresa->logo) }}" 
                                 alt="{{ $empresa->nombre_comercial ?? $empresa->razon_social }}" 
                                 style="max-width: 180px; margin-bottom: 15px;">
                            @endif
                            <h1 style="color: #FFFFFF; margin: 0; font-size: 26px; font-weight: bold;">
                                Cotización Disponible
                            </h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0 0; font-size: 14px; font-family: 'Courier New', monospace; font-weight: 700;">
                                {{ $cotizacion->folio }}
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 40px 30px; color: #2F2F2F;">
                            
                            <p style="font-size: 16px; margin: 0 0 20px 0;">
                                Hola <strong>{{ $cotizacion->cliente_nombre }}</strong>,
                            </p>
                            
                            <p style="font-size: 16px; margin: 0 0 25px 0; line-height: 1.6;">
                                Te compartimos tu cotización con la siguiente información:
                            </p>
                            
                            <!-- Tabla de Información -->
                            <table width="100%" cellpadding="12" cellspacing="0" style="background: #F3F4F6; border-radius: 8px; margin: 0 0 25px 0;">
                                <tr>
                                    <td style="font-weight: 600; color: #4B5563; font-size: 14px;">Folio:</td>
                                    <td style="color: #2F2F2F; font-size: 14px; text-align: right; font-weight: 600; font-family: 'Courier New', monospace;">
                                        {{ $cotizacion->folio }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; color: #4B5563; font-size: 14px; border-top: 1px solid #D1D5DB; padding-top: 12px;">
                                        Fecha:
                                    </td>
                                    <td style="color: #2F2F2F; font-size: 14px; text-align: right; border-top: 1px solid #D1D5DB; padding-top: 12px;">
                                        {{ $cotizacion->fecha->format('d/m/Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; color: #4B5563; font-size: 14px; border-top: 1px solid #D1D5DB; padding-top: 12px;">
                                        Vigencia:
                                    </td>
                                    <td style="color: #2F2F2F; font-size: 14px; text-align: right; border-top: 1px solid #D1D5DB; padding-top: 12px;">
                                        {{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; color: #4B5563; font-size: 14px; border-top: 1px solid #D1D5DB; padding-top: 12px;">
                                        Condición:
                                    </td>
                                    <td style="text-align: right; border-top: 1px solid #D1D5DB; padding-top: 12px;">
                                        @if($cotizacion->tipo_venta === 'credito')
                                            <span style="color: #F59E0B; font-weight: bold; font-size: 14px;">
                                                📅 CRÉDITO {{ $cotizacion->dias_credito_aplicados }} DÍAS
                                            </span>
                                        @else
                                            <span style="color: #10B981; font-weight: bold; font-size: 14px;">
                                                💵 CONTADO
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600; color: #4B5563; font-size: 16px; border-top: 2px solid #0B3C5D; padding-top: 12px;">
                                        Total:
                                    </td>
                                    <td style="color: #1F5F8B; font-size: 20px; text-align: right; font-weight: bold; border-top: 2px solid #0B3C5D; padding-top: 12px; font-family: 'Courier New', monospace;">
                                        ${{ number_format($cotizacion->total, 2) }} {{ $cotizacion->moneda }}
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Mensaje -->
                            <p style="font-size: 15px; margin: 0 0 10px 0; line-height: 1.6; color: #4B5563;">
                                📎 <strong>En este correo encontrarás adjunta tu cotización en formato PDF.</strong>
                            </p>
                            
                            <p style="font-size: 15px; margin: 0 0 25px 0; line-height: 1.6; color: #6B7280;">
                                Si tienes alguna duda o deseas realizar cambios, con gusto te apoyamos.
                            </p>
                            
                            <!-- Firma -->
                            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #E5E7EB;">
                                <p style="font-size: 15px; margin: 0 0 5px 0;">
                                    Saludos cordiales,
                                </p>
                                <p style="font-size: 16px; font-weight: bold; color: #0B3C5D; margin: 0;">
                                    {{ $empresa->nombre_comercial ?? $empresa->razon_social }}
                                </p>
                            </div>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background: #F3F4F6; padding: 30px; text-align: center; font-size: 13px; color: #6B7280; border-top: 1px solid #E5E7EB;">
                            <p style="margin: 0 0 8px 0; font-weight: 600; color: #374151;">
                                {{ $empresa->razon_social }}
                            </p>
                            <p style="margin: 0 0 8px 0;">
                                RFC: {{ $empresa->rfc }}
                            </p>
                            <p style="margin: 0 0 8px 0;">
                                📧 {{ $empresa->email }} · 📱 {{ $empresa->telefono }}
                            </p>
                            @if($empresa->calle)
                            <p style="margin: 0 0 12px 0;">
                                📍 {{ $empresa->calle }} {{ $empresa->numero_exterior }}, 
                                {{ $empresa->colonia }}, {{ $empresa->municipio }}, 
                                {{ $empresa->estado }} {{ $empresa->codigo_postal }}
                            </p>
                            @endif
                            <p style="margin: 15px 0 0 0; font-size: 12px; color: #9CA3AF;">
                                Este correo fue enviado automáticamente, por favor no responder directamente.
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>