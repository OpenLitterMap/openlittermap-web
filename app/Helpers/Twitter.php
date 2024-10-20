<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Abraham\TwitterOAuth\TwitterOAuth;

class Twitter
{
    public static function sendTweet (string $message): void
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
            catch (\Exception $exception)
            {
                \Log::info(['Twitter.sendMessage', $exception->getMessage()]);
            }
        }
    }

    public static function sendTweetWithImage (string $message, string $imagePath): void
    {
        $consumer_key = env('TWITTER_API_CONSUMER_KEY');
        $consumer_secret = env('TWITTER_API_CONSUMER_SECRET');
        $access_token = env('TWITTER_API_ACCESS_TOKEN');
        $access_token_secret = env('TWITTER_API_ACCESS_SECRET');

        if (app()->environment() === 'production' && $consumer_key !== null) {
            $connection = new TwitterOAuth(
                $consumer_key,
                $consumer_secret,
                $access_token,
                $access_token_secret
            );

        $media_id = null;

        // Step 1: Upload media using v1.1 endpoint if image is provided
        if ($imagePath && file_exists($imagePath)) {
            try {
                $media = $connection->upload('media/upload', ['media' => $imagePath]);

                if (!empty($media->media_id_string)) {
                    $media_id = $media->media_id_string;
                }
            } catch (\Exception $exception) {
                \Log::error(['Twitter.sendTweetWithMedia - Media Upload', $exception->getMessage()]);
            }
        }

        // Step 2: Post tweet using v2 endpoint with media reference if media was uploaded
        $tweet_data = [
            "text" => $message,
        ];

        if ($media_id) {
            $tweet_data['media'] = [
                "media_ids" => [$media_id]
            ];
        }

        try {
            $v2_connection = new TwitterOAuth(
                $consumer_key,
                $consumer_secret,
                $access_token,
                $access_token_secret
            );
            $v2_connection->setApiVersion('2'); // Set API version to v2

            $response = $v2_connection->post("tweets", $tweet_data, true);

            if ($v2_connection->getLastHttpCode() == 201) {
                \Log::info('Tweet with media posted successfully.');
            } else {
                \Log::error('Error posting tweet: ' . json_encode($response));
            }
        } catch (\Exception $exception) {
            \Log::error(['Twitter.sendTweetWithMedia', $exception->getMessage()]);
        }
        }
    }
}
