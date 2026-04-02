<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case REQUESTED = 'REQUESTED';
    case SHIPPED = 'SHIPPED';
}
