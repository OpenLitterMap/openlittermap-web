<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;
use Abraham\TwitterOAuth\TwitterOAuth;

class Twitter
{
    public static function sendTweet (string $message)
    {
        $consumer_key = env('TWITTER_API_CONSUMER_KEY');
        $consumer_secret = env('TWITTER_API_CONSUMER_SECRET');
        $access_token = env('TWITTER_API_ACCESS_TOKEN');
        $access_token_secret = env('TWITTER_API_ACCESS_SECRET');

        if (app()->environment() === 'production' && $consumer_key !== null)
        {
            $connection = new TwitterOAuth(
                $consumer_key,
                $consumer_secret,
                $access_token,
                $access_token_secret
            );

            $connection->setApiVersion('2');

            $tweet = [
                "text" => $message
            ];

            try
            {
                $status = $connection->post("tweets", $tweet, true);
            }
            catch (Exception $exception)
            {
                Log::info(['Twitter.sendMessage', $exception->getMessage()]);
            }
        }
    }
}
