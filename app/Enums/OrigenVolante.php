<?php

namespace App\Enums;

enum OrigenVolante: string
{
    /** Recepción inicial en Oficialía de Partes (RF-37). */
    case RecepcionInicial = 'recepcion_inicial';

    /** Turnado interno: no constituye una nueva recepción (RF-37). */
    case TurnadoInterno = 'turnado_interno';
}
