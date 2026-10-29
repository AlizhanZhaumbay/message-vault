<?php

namespace App\Enums;

enum DestroyReason: string
{
    case ReadOnce = 'read_once';
    case ViewLimitReached = 'view_limit_reached';
    case DestroyDateReached = 'destroy_date_reached';
}
