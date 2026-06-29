<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'location' => [
        'secret' => env('LOCATE_API_KEY')
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID', env('AWS_KEY')),
        'secret' => env('AWS_SECRET_ACCESS_KEY', env('AWS_SECRET')),
        'region' => env('AWS_DEFAULT_REGION', env('AWS_REGION', 'us-east-1')),
        // SNS topic that SES publishes bounce/complaint notifications to.
        // The webhook rejects any message whose TopicArn does not match.
        'topic_arn' => env('SES_SNS_TOPIC_ARN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'model' => App\Models\Users\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'openai' => [
        'key' => env('OPEN_AI_KEY')
    ],

    'twitter' => [
        'enabled' => env('TWITTER_ENABLED', false),
        'consumer_key' => env('TWITTER_API_CONSUMER_KEY'),
        'consumer_secret' => env('TWITTER_API_CONSUMER_SECRET'),
        'access_token' => env('TWITTER_API_ACCESS_TOKEN'),
        'access_secret' => env('TWITTER_API_ACCESS_SECRET'),
    ],

    'bluesky' => [
        'enabled' => env('BLUESKY_ENABLED', false),
        'identifier' => env('BLUESKY_IDENTIFIER'),
        'app_password' => env('BLUESKY_APP_PASSWORD'),
        'service' => env('BLUESKY_SERVICE', 'https://bsky.social'),
    ],

    'browsershot' => [
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH', '/snap/bin/chromium'),
    ],

];
