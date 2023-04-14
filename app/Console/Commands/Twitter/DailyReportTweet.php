<?php

namespace App\Console\Commands\Twitter;

use App\Models\Photo;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Abraham\TwitterOAuth\TwitterOAuth;


class DailyReportTweet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twitter:daily-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a daily report about OLM to Twitter OLM_bot account';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startOfYesterday = Carbon::yesterday()->startOfDay();
        $endOfYesterday = Carbon::yesterday()->endOfDay();

        // total users
        $users = User::whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->count();

        // total uploads/photos
        $photos = Photo::whereDate('created_at', '>=', $startOfYesterday)
            ->whereDate('created_at', '<=', $endOfYesterday)
            ->count();

        // total tags
        // new locations
        // total littercoin

        $consumer_key = env('TWITTER_API_CONSUMER_KEY');
        $consumer_secret = env('TWITTER_API_CONSUMER_SECRET');
        $access_token = env('TWITTER_API_ACCESS_TOKEN');
        $access_token_secret = env('TWITTER_API_ACCESS_SECRET');

        $connection = new TwitterOAuth(
            $consumer_key,
            $consumer_secret,
            $access_token,
            $access_token_secret
        );

        $connection->setApiVersion('2');

        $message = [
            "text" => "Yesterday we signed up $users users and uploaded $photos photos! #openlittermap"
        ];

        try
        {
            $status = $connection->post("tweets", $message, true);

//            not working yet, needs better error handling
//            if ($connection->getLastHttpCode() == 200) {
//                echo "Tweet posted successfully!";
//            } else {
//                echo "Error posting tweet!";
//            }
        }
        catch (\Exception $e)
        {
            \Log::info($e->getMessage());
        }
    }
}