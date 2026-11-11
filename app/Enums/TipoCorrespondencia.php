<?php

namespace App\Enums;

enum TipoCorrespondencia: string
{
    case Oficio = 'oficio';
    case Carta = 'carta';
    case Memorando = 'memorando';
    case Solicitud = 'solicitud';
    case Otro = 'otro';
}
