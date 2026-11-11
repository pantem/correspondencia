<?php

namespace App\Enums;

enum EstadoTurno: string
{
    case Pendiente = 'pendiente';
    case EnTramite = 'en_tramite';
    case Resuelto = 'resuelto';
    case Cancelado = 'cancelado';
}
