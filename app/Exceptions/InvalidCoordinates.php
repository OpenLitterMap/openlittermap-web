<?php

namespace App\Exceptions;

use Exception;

class InvalidCoordinates extends Exception
{
    // lat = 0, lon = 0
    protected $message = "invalid-coordinates";
}
