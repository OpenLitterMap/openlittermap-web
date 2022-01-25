<?php

namespace App\Exceptions;

use Exception;

class PhotoAlreadyUploaded extends Exception
{
    protected $message = "photo-already-uploaded";
}
