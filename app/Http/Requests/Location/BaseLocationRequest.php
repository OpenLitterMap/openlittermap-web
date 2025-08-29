<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
