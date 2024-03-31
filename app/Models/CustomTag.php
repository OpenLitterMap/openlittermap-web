<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomTag extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function notIncludeTags(): array
    {
        return [
            'word',
            'As of September 1, 2023 I am no longer an ambassador for OLM and no longer supporting it',
            'ListenToYourUsers',
            'Willingness to pay people real money but not paying respect to volunteers yields poor results.',
            'A negative leader casts a shadow, not a path worth following',
            'As of September 1, 2023 I am no longer an ambassador for OLM',
        ];
    }
}
