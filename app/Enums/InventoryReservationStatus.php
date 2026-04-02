<?php

namespace App\Enums;

enum InventoryReservationStatus: string
{
    case RESERVED = 'RESERVED';
    case CONFIRMED = 'CONFIRMED';
    case RELEASED = 'RELEASED';
}
