<?php

namespace App\Enums;

enum OutboxEventStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PUBLISHED = 'PUBLISHED';
    case DEAD_LETTER = 'DEAD_LETTER';
}
