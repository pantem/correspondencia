<?php

namespace App\Enums;

enum CanalRecepcion: string
{
    case Presencial = 'presencial';
    case Correo = 'correo';
    case CorreoElectronico = 'correo_electronico';
    case Mensajero = 'mensajero';
    case Otro = 'otro';
}
