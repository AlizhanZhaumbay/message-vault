<?php

namespace App\Exceptions;

use App\Models\Message;
use RuntimeException;

class MessageDestroyedException extends RuntimeException
{
    public function __construct(public readonly Message $vaultMessage)
    {
        parent::__construct('This message has been destroyed.');
    }
}
