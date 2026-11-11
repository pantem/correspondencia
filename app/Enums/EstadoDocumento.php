<?php

namespace App\Enums;

enum EstadoDocumento: string
{
    case Registrado = 'registrado';
    case EnTramite = 'en_tramite';
    case Resuelto = 'resuelto';
    case Cancelado = 'cancelado';
}
