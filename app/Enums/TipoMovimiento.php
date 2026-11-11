<?php

namespace App\Enums;

enum TipoMovimiento: string
{
    case Turnado = 'turnado';
    case Reasignacion = 'reasignacion';
    case Seguimiento = 'seguimiento';
    case Resolucion = 'resolucion';
    case Cancelacion = 'cancelacion';
    case Correccion = 'correccion';
}
