<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['email', 'sub_token'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($subscriber) {
            $subscriber->sub_token = \Illuminate\Support\Str::random(30);
        });
    }
}
