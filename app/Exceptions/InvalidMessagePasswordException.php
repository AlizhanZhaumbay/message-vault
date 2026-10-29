<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidMessagePasswordException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This message is password protected: the X-Message-Password header is missing or wrong.');
    }
}
