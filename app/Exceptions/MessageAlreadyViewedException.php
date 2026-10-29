<?php

namespace App\Exceptions;

use RuntimeException;

class MessageAlreadyViewedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The message has already been read and is sealed forever.');
    }
}
