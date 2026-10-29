<?php

namespace App\Exceptions;

use App\Models\Message;
use RuntimeException;

class MessageSealedException extends RuntimeException
{
    public function __construct(public readonly Message $vaultMessage)
    {
        parent::__construct('This message is sealed until its unlock date.');
    }
}
