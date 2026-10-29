<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Sealed = 'sealed';
    case Available = 'available';
    case Destroyed = 'destroyed';
}
