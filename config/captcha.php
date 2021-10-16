<?php

return [
    'secret' => env('MIX_GOOGLE_RECAPTCHA_SECRET'),
    'sitekey' => env('MIX_GOOGLE_RECAPTCHA_KEY'),
    'options' => [
        'timeout' => 30,
    ],
];
