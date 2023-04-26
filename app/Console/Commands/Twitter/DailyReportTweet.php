<?php

namespace App\Console\Commands\Twitter;

use App\Helpers\Twitter;
use Carbon\Carbon;
use App\Models\Photo;
use App\Models\User\User;
use App\Models\Location\Country;
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

        $countries = Country::whereDate('updated_at', '>=', $startOfYesterday)
            ->whereDate('updated_at', '<=', $endOfYesterday)
            ->count();

        $totalUsers = User::count();

        // total tags
        // new locations
        // total littercoin

        $message = "Today we signed up $users users and uploaded $photos photos from $countries countries!";
        $message .= " We now have $totalUsers users! #openlittermap #OLMbot 🌍";

        Twitter::sendTweet($message);
    }
}
