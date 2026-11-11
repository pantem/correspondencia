<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Volante {{ $documento->folio }} · v{{ $version }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5pt;
            color: #2b2b2b;
            margin: 0;
        }
        .banda {
            background: #8b1538;
            color: #ffffff;
            padding: 14px 18px;
        }
        .banda h1 {
            font-size: 15pt;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .banda .sub {
            font-size: 9.5pt;
            color: #f3d9e2;
            margin: 3px 0 0;
        }
        .marca {
            border-bottom: 3px solid #c9961a;
            height: 6px;
        }
        .cuerpo { padding: 16px 18px 8px; }
        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { vertical-align: top; padding: 0; }
        .folio-caja {
            border: 1.5px solid #8b1538;
            padding: 8px 12px;
            text-align: center;
            width: 210px;
        }
        .folio-caja .etiqueta {
            font-size: 8pt;
            color: #8b1538;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .folio-caja .folio {
            font-size: 20pt;
            font-weight: bold;
            color: #8b1538;
        }
        .qr-caja { text-align: center; width: 150px; }
        .qr-caja img { width: 130px; height: 130px; }
        .qr-caja .pie { font-size: 7pt; color: #666; margin-top: 2px; }
        .sello-origen {
            margin-top: 14px;
            padding: 7px 10px;
            font-size: 9.5pt;
            font-weight: bold;
            text-align: center;
        }
        .sello-origen.inicial { background: #fbf0f4; border: 1.5px solid #8b1538; color: #8b1538; }
        .sello-origen.interno { background: #fdf6e3; border: 1.5px dashed #c9961a; color: #7a5c05; }
        .datos { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .datos td { border: 1px solid #e4d9de; padding: 6px 8px; vertical-align: top; }
        .datos .etiqueta {
            display: block;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #8b1538;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .datos .ancho { width: 50%; }
        .completo { width: 100%; }
        .movimiento {
            margin-top: 12px;
            background: #fbf7f3;
            border-left: 4px solid #c9961a;
            padding: 8px 10px;
            font-size: 9pt;
        }
        .movimiento strong { color: #3f1220; }
        .pie {
            margin-top: 16px;
            border-top: 1px solid #e4d9de;
            padding-top: 8px;
            font-size: 8pt;
            color: #666;
        }
        .pie .fila { margin-bottom: 2px; }
        .nota {
            margin-top: 6px;
            font-size: 7.5pt;
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="banda">
        <h1>COMISIÓN PARA LA RECONSTRUCCIÓN</h1>
        <p class="sub">Sistema de Correspondencia · Volante de turno · Versión {{ $version }}</p>
    </div>
    <div class="marca"></div>

    <div class="cuerpo">
        <table class="layout">
            <tr>
                <td>
                    <div class="folio-caja">
                        <div class="etiqueta">Folio principal</div>
                        <div class="folio">{{ $documento->folio }}</div>
                    </div>
                </td>
                <td style="text-align:center;">
                    <table style="margin:0 auto;"><tr>
                        <td style="padding-right:10px;">
                            <div class="sello-origen {{ $origen->value === 'recepcion_inicial' ? 'inicial' : 'interno' }}" style="margin-top:6px; width:250px;">
                                @if ($origen->value === 'recepcion_inicial')
                                    RECEPCIÓN INICIAL EN OFICIALÍA DE PARTES
                                @else
                                    TURNADO INTERNO<br><span style="font-weight:normal; font-size:8pt;">No constituye una nueva recepción en Oficialía de Partes</span>
                                @endif
                            </div>
                        </td>
                        <td class="qr-caja">
                            <img src="{{ $qrImagen }}" alt="QR">
                            <div class="pie">Consulte el estado con este folio</div>
                        </td>
                    </tr></table>
                </td>
            </tr>
        </table>

        <table class="datos">
            <tr>
                <td class="ancho">
                    <span class="etiqueta">Procedencia</span>
                    {{ $documento->sentido->value === 'recibida' ? 'Correspondencia recibida' : 'Correspondencia enviada' }}
                    @if ($documento->remitente) · {{ $documento->remitente }} @endif
                    @if ($documento->dependencia) · {{ $documento->dependencia }} @endif
                </td>
                <td class="ancho">
                    <span class="etiqueta">Recepción</span>
                    {{ ($documento->fecha_recepcion ?? $documento->fecha_documento)?->format('d/m/Y') ?? '—' }}
                    @if ($documento->oficio_original) · Oficio original: {{ $documento->oficio_original }} @endif
                </td>
            </tr>
            <tr>
                <td class="completo">
                    <span class="etiqueta">Asunto</span>
                    {{ $documento->asunto }}
                </td>
            </tr>
            <tr>
                <td class="completo">
                    <span class="etiqueta">Observaciones</span>
                    {{ $documento->observaciones ?: '—' }}
                </td>
            </tr>
            <tr>
                <td class="ancho">
                    <span class="etiqueta">Destinatario (área destino)</span>
                    {{ $turno->areaDestino?->nombre ?? '—' }}
                    @if ($turno->areaDestino?->siglas) ({{ $turno->areaDestino->siglas }}) @endif
                </td>
                <td class="ancho">
                    <span class="etiqueta">Responsable vigente</span>
                    {{ $turno->responsable?->name ?? 'Sin asignar' }}
                </td>
            </tr>
            <tr>
                <td class="ancho">
                    <span class="etiqueta">Tipo de asignación</span>
                    {{ $turno->tipo_asignacion->value === 'atencion' ? 'Atención (requiere respuesta)' : 'Conocimiento (no exige respuesta)' }}
                </td>
                <td class="ancho">
                    <span class="etiqueta">Plazo aplicable</span>
                    @if ($turno->tipo_asignacion->value === 'conocimiento')
                        Sin plazo — el conocimiento no vence
                    @elseif ($turno->plazo)
                        {{ $turno->plazo->format('d/m/Y') }}{{ $turno->plazo->isPast() ? ' (vencido)' : '' }}
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td class="completo">
                    <span class="etiqueta">Instrucciones</span>
                    {{ $turno->instrucciones ?: '—' }}
                </td>
            </tr>
        </table>

        <div class="movimiento">
            <strong>Datos del movimiento:</strong>
            @if ($movimiento)
                {{ ucfirst($movimiento->tipo->value) }} · {{ $movimiento->fecha->format('d/m/Y H:i') }} ·
                Registrado por {{ $movimiento->usuarioOrigen?->name ?? '—' }}
                @if ($movimiento->notas) — {{ $movimiento->notas }} @endif
            @else
                Turno generado directamente.
            @endif
            · Prioridad: {{ $documento->prioridad->value }}
            · Estado del turno: {{ str_replace('_', ' ', $turno->estado->value) }}
        </div>

        <div class="pie">
            <div class="fila"><strong>Volante v{{ $version }}</strong> del turno #{{ $turno->id }} · Folio {{ $documento->folio }}-{{ $documento->anio }}</div>
            <div class="fila">Emitido por {{ $emitidoPor->name }} el {{ $emitidoAt->format('d/m/Y H:i') }}</div>
            <div class="fila">Documento generado electrónicamente por el Sistema de Correspondencia de la Comisión para la Reconstrucción.</div>
            <div class="nota">Este volante ampara el turno descrito. Turnados internos no representan una recepción nueva en Oficialía de Partes.</div>
        </div>
    </div>
</body>
</html>
